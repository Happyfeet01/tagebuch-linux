from __future__ import annotations

import argparse
import getpass
import hashlib
import json
import os
import sys
from datetime import date, datetime, time, timedelta
from pathlib import Path

from . import __version__
from .config import APP_NAME, Config
from .exchange import (
    export_html,
    export_json,
    export_markdown,
    export_txt,
    import_csv,
    import_json,
    import_markdown_dir,
    stars,
)
from .lock import LockManager
from .model import Entry, Location
from .search import SearchFilter, all_tags, search_entries
from .stats import compute_stats, on_this_day
from .store import JournalStore
from .sync import SyncEngine
from .webdav import WebDAVClient


def _data_dir() -> Path:
    base = os.environ.get("XDG_DATA_HOME") or str(Path.home() / ".local" / "share")
    return Path(base) / APP_NAME


def _sync_state_path(config: Config) -> Path:
    url = config.get("webdav", "url") or "local"
    key = hashlib.sha256(url.encode("utf-8")).hexdigest()[:16]
    return _data_dir() / "sync-state" / f"{key}.json"


def _store(config: Config) -> JournalStore:
    return JournalStore(config.get("journal_path"))


def _build_engine(config: Config) -> SyncEngine:
    webdav = config.get("webdav")
    url = (webdav.get("url") or "").rstrip("/")
    if not url:
        raise SystemExit(
            "WebDAV nicht konfiguriert. Erst: tagebuch webdav setup --url ... --username ..."
        )
    remote = (webdav.get("remote_path") or "").strip("/")
    full_url = f"{url}/{remote}" if remote else url
    client = WebDAVClient(full_url, webdav.get("username", ""), config.webdav_password())
    if remote:
        client.ensure_dir(remote)
    return SyncEngine(_store(config), client, _sync_state_path(config))


def _parse_date(value: str) -> date:
    try:
        return date.fromisoformat(value)
    except ValueError:
        parts = value.split(".")
        if len(parts) == 3:
            return date(int(parts[2]), int(parts[1]), int(parts[0]))
        raise SystemExit(f"Ungueltiges Datum: {value!r} (erwartet YYYY-MM-DD)") from None


def _parse_time(value: str) -> time:
    try:
        return time.fromisoformat(value)
    except ValueError:
        raise SystemExit(f"Ungueltige Uhrzeit: {value!r} (erwartet HH:MM)") from None


def _parse_tracker(value: str):
    if "=" not in value:
        raise SystemExit(f"Tracker erwartet key=value, erhalten: {value!r}")
    key, raw = value.split("=", 1)
    try:
        return key.strip(), int(raw)
    except ValueError:
        try:
            return key.strip(), float(raw)
        except ValueError:
            return key.strip(), raw.strip()


def _format_entry_line(entry: Entry) -> str:
    stamp = entry.date.isoformat()
    if entry.time:
        stamp += f" {entry.time.strftime('%H:%M')}"
    rating = f" {stars(entry.rating)}" if entry.rating is not None else ""
    tags = f" {' '.join('#' + t for t in entry.tags)}" if entry.tags else ""
    heading = f" {entry.heading}" if entry.heading else ""
    snippet = entry.body.strip().splitlines()[0] if entry.body.strip() else ""
    snippet = snippet[:60]
    return f"{stamp}{rating}{tags}{heading}  {snippet}"


def cmd_init(args, config: Config) -> int:
    config.set("journal_path", str(Path(args.path).expanduser()))
    _store(config).init()
    print(f"Tagebuch angelegt: {config.get('journal_path')}")
    return 0


def cmd_config(args, config: Config) -> int:
    if args.value is not None:
        section, _, key = args.key.partition(".")
        if not key:
            raise SystemExit("Schluessel im Format bereich.key angeben, z. B. reminder.time")
        value: object = args.value
        if args.value.lower() in ("true", "false"):
            value = args.value.lower() == "true"
        elif args.value.isdigit():
            value = int(args.value)
        config.set(section, key, value)
        print(f"{args.key} = {value}")
        return 0
    value = config.get(args.key.partition(".")[0], args.key.partition(".")[2])
    print(json.dumps(value, ensure_ascii=False) if isinstance(value, (dict, list)) else value)
    return 0


def cmd_webdav(args, config: Config) -> int:
    config.set("webdav", "url", args.url.rstrip("/"))
    config.set("webdav", "username", args.username)
    if args.remote_path:
        config.set("webdav", "remote_path", args.remote_path.strip("/"))
    password = getpass.getpass(f"App-Passwort fuer {args.username}: ")
    config.set_webdav_password(password)
    remote = (args.remote_path or config.get("webdav", "remote_path") or "").strip("/")
    full = f"{args.url.rstrip('/')}/{remote}" if remote else args.url.rstrip("/")
    client = WebDAVClient(full, args.username, password)
    if remote:
        client.ensure_dir(remote)
    items = client.list()
    print(f"WebDAV verbunden: {full} ({len(items)} Eintraege im Remote-Ordner)")
    return 0


def cmd_new(args, config: Config) -> int:
    store = _store(config)
    day = _parse_date(args.date) if args.date else date.today()
    at = _parse_time(args.time) if args.time else None
    if at is None and store.get_entry(day) is not None:
        now = datetime.now().time()
        at = now.replace(second=0, microsecond=0)
    location = None
    if args.location:
        location = Location(name=args.location, lat=args.lat, lon=args.lon)
    entry = Entry(
        date=day,
        time=at,
        heading=args.heading or "",
        body=" ".join(args.text),
        rating=args.rating,
        tags=args.tag or [],
        trackers=dict(_parse_tracker(t) for t in (args.tracker or [])),
        location=location,
    )
    for attachment in args.attach or []:
        entry.attachments.append(store.add_attachment(day, attachment))
    path = store.save_entry(entry)
    print(f"Gespeichert: {path}")
    return 0


def cmd_list(args, config: Config) -> int:
    store = _store(config)
    entries = store.list_entries()
    if args.days:
        cutoff = date.today() - timedelta(days=args.days)
        entries = [e for e in entries if e.date >= cutoff]
    for entry in entries:
        print(_format_entry_line(entry))
    if not entries:
        print("Keine Eintraege.")
    return 0


def cmd_show(args, config: Config) -> int:
    store = _store(config)
    day = _parse_date(args.date)
    at = _parse_time(args.time) if args.time else None
    entry = store.get_entry(day, at)
    if entry is None:
        matches = store.entries_for_day(day)
        if len(matches) == 1:
            entry = matches[0]
    if entry is None:
        print(f"Kein Eintrag fuer {day.isoformat()} gefunden.", file=sys.stderr)
        return 1
    print(entry.to_markdown(), end="")
    return 0


def cmd_search(args, config: Config) -> int:
    store = _store(config)
    filt = SearchFilter(
        query=" ".join(args.query),
        tag=args.tag,
        since=_parse_date(args.since) if args.since else None,
        until=_parse_date(args.until) if args.until else None,
        min_rating=args.min_rating,
    )
    results = search_entries(store, filt)
    for entry in results:
        print(_format_entry_line(entry))
    print(f"{len(results)} Treffer")
    return 0


def cmd_tags(args, config: Config) -> int:
    for tag in all_tags(_store(config)):
        print(f"#{tag}")
    return 0


def cmd_stats(args, config: Config) -> int:
    stats = compute_stats(_store(config).list_entries())
    print(f"Eintraege gesamt:   {stats['total_entries']}")
    print(f"Tage mit Eintrag:   {stats['total_days']}")
    print(f"Woerter gesamt:     {stats['total_words']}")
    print(f"Aktuelle Serie:     {stats['current_streak']} Tage")
    print(f"Laengste Serie:     {stats['longest_streak']} Tage")
    if stats["average_rating"] is not None:
        print(f"Durchschn. Bewertung: {stats['average_rating']} / 5")
    print("Pro Monat:")
    for month, count in stats["per_month"].items():
        print(f"  {month}: {count}")
    return 0


def cmd_on_this_day(args, config: Config) -> int:
    results = on_this_day(_store(config).list_entries())
    for entry in results:
        print(_format_entry_line(entry))
    if not results:
        print("Keine Eintraege an diesem Tag in frueheren Jahren.")
    return 0


def cmd_sync(args, config: Config) -> int:
    result = _build_engine(config).sync()
    print(f"Hochgeladen:   {len(result.uploaded)}")
    print(f"Heruntergeladen: {len(result.downloaded)}")
    print(f"Remote geloescht: {len(result.deleted_remote)}")
    print(f"Lokal geloescht:  {len(result.deleted_local)}")
    print(f"Konflikte:     {len(result.conflicts)}")
    for conflict in result.conflicts:
        print(f"  Konflikt: {conflict} (Remote-Version als *.conflict-* lokal gesichert)")
    for error in result.errors:
        print(f"  Fehler: {error}", file=sys.stderr)
    return 1 if result.errors else 0


def cmd_export(args, config: Config) -> int:
    store = _store(config)
    dest = Path(args.dest)
    if args.format == "json":
        count = export_json(store, dest)
    elif args.format == "md":
        count = export_markdown(store, dest)
    elif args.format == "txt":
        count = export_txt(store, dest)
    elif args.format == "html":
        count = export_html(store, dest)
    else:
        raise SystemExit(f"Unbekanntes Format: {args.format}")
    print(f"{count} Eintraege exportiert nach {dest} ({args.format})")
    return 0


def cmd_import(args, config: Config) -> int:
    store = _store(config)
    src = Path(args.src)
    if src.is_dir():
        count = import_markdown_dir(store, src)
        kind = "Markdown-Ordner"
    elif src.suffix.lower() == ".json":
        count = import_json(store, src)
        kind = "JSON"
    elif src.suffix.lower() == ".csv":
        count = import_csv(store, src)
        kind = "CSV"
    else:
        raise SystemExit("Nicht unterstuetzt: .json, .csv oder ein Ordner mit .md-Dateien")
    print(f"{count} Eintraege importiert ({kind}: {src})")
    return 0


def cmd_lock(args, config: Config) -> int:
    lock = LockManager(config)
    if args.action == "status":
        print(f"Sperre aktiv: {lock.enabled}")
        return 0
    if args.action == "set-pin":
        pin = getpass.getpass("Neue PIN: ")
        confirm = getpass.getpass("PIN wiederholen: ")
        if pin != confirm:
            raise SystemExit("PINs stimmen nicht ueberein")
        lock.set_pin(pin)
        print("PIN gesetzt, Sperre aktiv.")
        return 0
    if args.action == "change-pin":
        old = getpass.getpass("Alte PIN: ")
        new = getpass.getpass("Neue PIN: ")
        lock.change_pin(old, new)
        print("PIN geaendert.")
        return 0
    if args.action == "disable":
        pin = getpass.getpass("PIN zur Deaktivierung: ")
        lock.disable(pin)
        print("Sperre deaktiviert.")
        return 0
    raise SystemExit(f"Unbekannte Aktion: {args.action}")


def cmd_template(args, config: Config) -> int:
    store = _store(config)
    if args.action == "add":
        if not args.file:
            raise SystemExit("template add erwartet eine Datei: tagebuch template add DATEI")
        name = store.add_template(args.file)
        print(f"Vorlage gespeichert: {name}")
        return 0
    if args.action == "list":
        for name in store.list_templates():
            print(name)
        return 0
    if args.action == "show":
        name = args.name or args.file
        if not name:
            raise SystemExit("template show erwartet einen Namen: tagebuch template show NAME")
        print(store.get_template(name), end="")
        return 0
    raise SystemExit(f"Unbekannte Aktion: {args.action}")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="tagebuch", description="Privates Tagebuch (Markdown + Nextcloud/WebDAV)"
    )
    parser.add_argument("--version", action="version", version=f"%(prog)s {__version__}")
    sub = parser.add_subparsers(dest="command", required=True)

    p = sub.add_parser("init", help="Lokales Tagebuch anlegen")
    p.add_argument("path")
    p.set_defaults(func=cmd_init)

    p = sub.add_parser("config", help="Konfiguration anzeigen/setzen (z. B. reminder.time 21:00)")
    p.add_argument("key")
    p.add_argument("value", nargs="?", default=None)
    p.set_defaults(func=cmd_config)

    p = sub.add_parser("webdav", help="WebDAV/Nextcloud-Zugang einrichten und testen")
    p.add_argument("setup", choices=["setup"])
    p.add_argument("--url", required=True, help="z. B. https://cloud.example/remote.php/dav/files/BENUTZER")
    p.add_argument("--username", required=True)
    p.add_argument("--remote-path", default="", help="Unterordner im WebDAV, z. B. Tagebuch")
    p.set_defaults(func=cmd_webdav)

    p = sub.add_parser("new", help="Neuen Eintrag schreiben")
    p.add_argument("text", nargs="+")
    p.add_argument("--date", default=None)
    p.add_argument("--time", default=None)
    p.add_argument("--heading", default="")
    p.add_argument("--tag", action="append")
    p.add_argument("--rating", type=int, default=None, choices=range(1, 6))
    p.add_argument("--tracker", action="append", help="key=value")
    p.add_argument("--location", default=None)
    p.add_argument("--lat", type=float, default=None)
    p.add_argument("--lon", type=float, default=None)
    p.add_argument("--attach", action="append", help="Datei als Anhang")
    p.set_defaults(func=cmd_new)

    p = sub.add_parser("list", help="Eintraege auflisten")
    p.add_argument("--days", type=int, default=None)
    p.set_defaults(func=cmd_list)

    p = sub.add_parser("show", help="Eintrag als Markdown anzeigen")
    p.add_argument("date")
    p.add_argument("--time", default=None)
    p.set_defaults(func=cmd_show)

    p = sub.add_parser("search", help="Volltextsuche")
    p.add_argument("query", nargs="+")
    p.add_argument("--tag", default=None)
    p.add_argument("--since", default=None)
    p.add_argument("--until", default=None)
    p.add_argument("--min-rating", type=int, default=None)
    p.set_defaults(func=cmd_search)

    p = sub.add_parser("tags", help="Alle Tags auflisten")
    p.set_defaults(func=cmd_tags)

    p = sub.add_parser("stats", help="Statistiken")
    p.set_defaults(func=cmd_stats)

    p = sub.add_parser("on-this-day", help="Eintraege an diesem Tag in frueheren Jahren")
    p.set_defaults(func=cmd_on_this_day)

    p = sub.add_parser("sync", help="Mit WebDAV/Nextcloud synchronisieren")
    p.set_defaults(func=cmd_sync)

    p = sub.add_parser("export", help="Tagebuch exportieren")
    p.add_argument("dest")
    p.add_argument("--format", choices=["json", "md", "txt", "html"], required=True)
    p.set_defaults(func=cmd_export)

    p = sub.add_parser("import", help="Import aus JSON/CSV/Markdown-Ordner")
    p.add_argument("src")
    p.set_defaults(func=cmd_import)

    p = sub.add_parser("lock", help="PIN-Sperre verwalten")
    p.add_argument("action", choices=["set-pin", "change-pin", "disable", "status"])
    p.set_defaults(func=cmd_lock)

    p = sub.add_parser("template", help="Vorlagen verwalten")
    p.add_argument("action", choices=["add", "list", "show"])
    p.add_argument("file", nargs="?", default=None)
    p.add_argument("--name", dest="name", default=None)
    p.set_defaults(func=cmd_template)

    return parser


def main(argv: list[str] | None = None, config: Config | None = None) -> int:
    args = build_parser().parse_args(argv)
    config = config or Config()
    return args.func(args, config)


if __name__ == "__main__":
    raise SystemExit(main())
