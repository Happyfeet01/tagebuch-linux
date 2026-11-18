from __future__ import annotations

import sys
import threading
from datetime import datetime
from pathlib import Path

import gi

gi.require_version("Gtk", "4.0")
gi.require_version("Adw", "1")
from gi.repository import Adw, Gdk, Gio, GLib, Gtk  # noqa: E402

from . import __version__  # noqa: E402
from .cli import _sync_state_path  # noqa: E402
from .config import Config  # noqa: E402
from .exchange import (  # noqa: E402
    export_html,
    export_json,
    export_markdown,
    export_txt,
    import_csv,
    import_json,
    import_markdown_dir,
)
from .lock import LockManager  # noqa: E402
from .reminders import next_fire, should_fire  # noqa: E402
from .store import JournalStore  # noqa: E402
from .sync import SyncEngine  # noqa: E402
from .ui.lock_screen import LockScreen  # noqa: E402
from .ui.settings_dialog import SettingsDialog  # noqa: E402
from .ui.window import MainWindow  # noqa: E402
from .webdav import WebDAVClient  # noqa: E402

APP_ID = "io.github.happyfeet01.tagebuchlinux"

CSS = """
.today { font-weight: bold; border: 2px solid @accent_color; border-radius: 8px; }
.has-entry { font-weight: bold; }
.rating-5 { color: #e6a700; }
.rating-4 { color: #d3a013; }
.rating-3 { color: #8f7b3a; }
.rating-2 { color: #a06a6a; }
.rating-1 { color: #c64545; }
.stars { color: #e6a700; }
"""


class TagebuchApp(Adw.Application):
    def __init__(self):
        super().__init__(application_id=APP_ID)
        self.config = Config()
        self.store = JournalStore(self.config.get("journal_path"))
        self.lock = LockManager(self.config)
        self.window: MainWindow | None = None
        self._last_fired_reminder: object = None
        self._last_activity = datetime.now()

    def do_activate(self) -> None:
        if self.window is None:
            self.window = MainWindow(self, self.store)
            self._register_actions()
        self.window.present()
        self._apply_appearance()
        if self.lock.enabled:
            self.lock.lock()
            self._show_lock_screen()
        self._schedule_reminder()
        self._schedule_autolock()

    def _register_actions(self) -> None:
        for name, callback in [
            ("settings", lambda *_: self._open_settings()),
            ("export", lambda *_: self._export_dialog()),
            ("import", lambda *_: self._import_dialog()),
            ("lock", lambda *_: self._lock_now()),
            ("about", lambda *_: self._about()),
        ]:
            action = Gio.SimpleAction.new(name, None)
            action.connect("activate", callback)
            self.add_action(action)
        insert_tpl = Gio.SimpleAction.new_string("insert-template", None)
        insert_tpl.connect("activate", lambda a, param: self._insert_template(param.get_string()))
        self.add_action(insert_tpl)

    def _apply_appearance(self) -> None:
        provider = Gtk.CssProvider()
        provider.load_from_string(CSS)
        display = Gdk.Display.get_default()
        if display is not None:
            Gtk.StyleContext.add_provider_for_display(
                display, provider, Gtk.STYLE_PROVIDER_PRIORITY_APPLICATION
            )
        manager = Adw.StyleManager.get_default()
        theme = self.config.get("appearance", "theme") or "system"
        manager.set_color_scheme(
            {
                "system": Adw.ColorScheme.DEFAULT,
                "light": Adw.ColorScheme.FORCE_LIGHT,
                "dark": Adw.ColorScheme.FORCE_DARK,
            }.get(theme, Adw.ColorScheme.DEFAULT)
        )

    def _show_lock_screen(self) -> None:
        if self.window is None:
            return
        screen = LockScreen(self.window, self.lock, on_unlock=lambda: self.window.refresh())
        screen.present()

    def _lock_now(self) -> None:
        self.lock.lock()
        self._show_lock_screen()

    def _schedule_autolock(self) -> None:
        minutes = self.config.get("lock", "auto_lock_minutes") or 5
        GLib.timeout_add_seconds(30, self._check_autolock, minutes)

    def _check_autolock(self, minutes: int) -> bool:
        if self.lock.enabled and not self.lock.is_locked:
            idle = (datetime.now() - self._last_activity).total_seconds()
            if idle >= minutes * 60:
                self._lock_now()
        return True

    def _schedule_reminder(self) -> None:
        if not self.config.get("reminder", "enabled"):
            return
        time_str = self.config.get("reminder", "time") or "21:00"
        delay = (next_fire(datetime.now(), time_str) - datetime.now()).total_seconds()
        GLib.timeout_add_seconds(max(1, int(delay)), self._fire_reminder, time_str)

    def _fire_reminder(self, time_str: str) -> bool:
        now = datetime.now()
        today_entries = self.store.entries_for_day(now.date())
        if should_fire(now, time_str, self._last_fired_reminder, bool(today_entries)):
            self._last_fired_reminder = now.date()
            notification = Gio.Notification.new("Tagebuch")
            notification.set_body("Zeit für deinen heutigen Eintrag.")
            notification.set_priority(Gio.NotificationPriority.HIGH)
            self.send_notification("reminder", notification)
        GLib.timeout_add_seconds(
            max(60, int((next_fire(datetime.now(), time_str) - datetime.now()).total_seconds())),
            self._fire_reminder,
            time_str,
        )
        return False

    def _open_settings(self) -> None:
        if self.window is None:
            return
        dialog = SettingsDialog(self.window, self.config, on_webdav_changed=None)
        dialog.present(self.window)

    def _export_dialog(self) -> None:
        if self.window is None:
            return
        dialog = Gtk.FileDialog()
        dialog.set_initial_name("tagebuch-export.json")
        dialog.save(self.window, None, lambda d, res: self._export_finish(d, res))

    def _export_finish(self, dialog: Gtk.FileDialog, result) -> None:
        try:
            file = dialog.save_finish(result)
        except Exception:
            return
        if file is None:
            return
        path = Path(file.get_path())
        suffix = path.suffix.lower()
        if suffix == ".html":
            count = export_html(self.store, path)
        elif suffix == ".txt":
            count = export_txt(self.store, path)
        elif suffix == ".md":
            count = export_markdown(self.store, path)
        else:
            count = export_json(self.store, path.with_suffix(".json"))
            path = path.with_suffix(".json")
        self.window.show_toast(f"{count} Einträge exportiert nach {path.name}")

    def _import_dialog(self) -> None:
        if self.window is None:
            return
        dialog = Gtk.FileDialog()
        dialog.open(self.window, None, lambda d, res: self._import_finish(d, res))

    def _import_finish(self, dialog: Gtk.FileDialog, result) -> None:
        try:
            file = dialog.open_finish(result)
        except Exception:
            return
        if file is None:
            return
        path = Path(file.get_path())
        suffix = path.suffix.lower()
        if suffix == ".json":
            count = import_json(self.store, path)
        elif suffix == ".csv":
            count = import_csv(self.store, path)
        else:
            count = import_markdown_dir(self.store, path.parent)
        self.window.show_toast(f"{count} Einträge importiert")
        self.window.refresh()

    def _insert_template(self, name: str) -> None:
        if self.window is None:
            return
        page = self.window.nav.get_visible_page()
        if isinstance(page, object) and hasattr(page, "insert_template"):
            page.insert_template(name)

    def _about(self) -> None:
        dialog = Adw.AboutDialog(
            application_name="Tagebuch",
            version=__version__,
            developer_name="Happyfeet01",
            license_type=Gtk.License.MIT_X11,
            comments=(
                "Privates Tagebuch für Linux: Einträge als Markdown (*.md) "
                "in der eigenen Nextcloud (WebDAV). Funktions-Nachbau von Diarium."
            ),
        )
        if self.window is not None:
            dialog.present(self.window)

    def build_engine(self) -> SyncEngine:
        webdav = self.config.get("webdav")
        url = (webdav.get("url") or "").rstrip("/")
        remote = (webdav.get("remote_path") or "").strip("/")
        full_url = f"{url}/{remote}" if remote else url
        client = WebDAVClient(full_url, webdav.get("username", ""), self.config.webdav_password())
        if remote:
            client.ensure_dir(remote)
        return SyncEngine(self.store, client, _sync_state_path(self.config))

    def sync_now(self) -> None:
        if self.window is None:
            return
        if not self.config.get("webdav", "url"):
            self.window.show_toast("WebDAV zuerst in den Einstellungen konfigurieren")
            return
        self.window.set_syncing(True)

        def work() -> None:
            try:
                result = self.build_engine().sync()
                GLib.idle_add(self._sync_done, result)
            except Exception as exc:
                GLib.idle_add(self._sync_failed, str(exc))

        threading.Thread(target=work, daemon=True).start()

    def _sync_done(self, result) -> bool:
        if self.window is not None:
            self.window.set_syncing(False)
            parts = [
                f"↑ {len(result.uploaded)}",
                f"↓ {len(result.downloaded)}",
                f"Konflikte: {len(result.conflicts)}",
            ]
            if result.errors:
                parts.append(f"Fehler: {len(result.errors)}")
            self.window.show_toast("Sync: " + "  ".join(parts), timeout=5)
            self.window.refresh()
        return False

    def _sync_failed(self, message: str) -> bool:
        if self.window is not None:
            self.window.set_syncing(False)
            self.window.show_toast(f"Sync fehlgeschlagen: {message}", timeout=6)
        return False


def main() -> int:
    app = TagebuchApp()
    return app.run(sys.argv)


if __name__ == "__main__":
    raise SystemExit(main())
