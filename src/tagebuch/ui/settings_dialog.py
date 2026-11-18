from __future__ import annotations

import contextlib
import getpass

import gi

gi.require_version("Gtk", "4.0")
gi.require_version("Adw", "1")
from gi.repository import Adw, Gtk  # noqa: E402

from ..config import Config  # noqa: E402
from ..lock import LockManager  # noqa: E402

THEMES = ["System", "Hell", "Dunkel"]
ACCENTS = ["blau", "grün", "rot", "orange", "violett", "gelb"]


class PasswordDialog(Gtk.Window):
    def __init__(self, parent, title: str, on_done):
        super().__init__(modal=True, transient_for=parent, title=title)
        self.on_done = on_done
        box = Gtk.Box(orientation=Gtk.Orientation.VERTICAL, spacing=10)
        box.set_margin_top(16)
        box.set_margin_bottom(16)
        box.set_margin_start(16)
        box.set_margin_end(16)
        self.entry = Gtk.PasswordEntry()
        self.entry.set_show_peek_button(True)
        self.entry.set_placeholder_text("App-Passwort")
        self.entry.connect("activate", lambda *_: self._done())
        box.append(self.entry)
        btn = Gtk.Button(label="Speichern")
        btn.add_css_class("suggested-action")
        btn.connect("clicked", lambda *_: self._done())
        box.append(btn)
        self.set_child(box)
        self.entry.grab_focus()

    def _done(self) -> None:
        self.on_done(self.entry.get_text())
        self.close()


class SettingsDialog(Adw.PreferencesDialog):
    def __init__(self, window, config: Config, on_webdav_changed=None):
        super().__init__()
        self.config = config
        self.window = window
        self.on_webdav_changed = on_webdav_changed
        self.set_title("Einstellungen")

        page = Adw.PreferencesPage()

        group = Adw.PreferencesGroup(title="Tagebuch")
        path_row = Adw.ActionRow(title="Lokaler Ordner", subtitle=config.get("journal_path"))
        change_btn = Gtk.Button(label="Ändern", valign=Gtk.Align.CENTER)
        change_btn.connect("clicked", self._on_change_path, path_row)
        path_row.add_suffix(change_btn)
        group.add(path_row)
        page.add(group)

        group = Adw.PreferencesGroup(title="Synchronisation (WebDAV / Nextcloud)")
        self.url_row = Adw.EntryRow(title="Server-URL")
        self.url_row.set_text(config.get("webdav", "url") or "")
        self.url_row.connect("changed", self._save_webdav)
        group.add(self.url_row)
        self.user_row = Adw.EntryRow(title="Benutzername")
        self.user_row.set_text(config.get("webdav", "username") or "")
        self.user_row.connect("changed", self._save_webdav)
        group.add(self.user_row)
        self.remote_row = Adw.EntryRow(title="Remote-Ordner")
        self.remote_row.set_text(config.get("webdav", "remote_path") or "")
        self.remote_row.connect("changed", self._save_webdav)
        group.add(self.remote_row)
        if config.get("webdav", "use_keyring"):
            pw_subtitle = "Im Schlüsselbund gespeichert"
        else:
            pw_subtitle = "In Config-Datei (0600) gespeichert"
        pw_row = Adw.ActionRow(title="App-Passwort", subtitle=pw_subtitle)
        pw_btn = Gtk.Button(label="Setzen/ändern", valign=Gtk.Align.CENTER)
        pw_btn.connect("clicked", self._on_set_password)
        pw_row.add_suffix(pw_btn)
        group.add(pw_row)
        self.auto_sync = Adw.SwitchRow(
            title="Automatisch synchronisieren", active=bool(config.get("sync", "auto_sync"))
        )
        self.auto_sync.connect(
            "notify::active", lambda row, _: config.set("sync", "auto_sync", row.get_active())
        )
        group.add(self.auto_sync)
        page.add(group)

        group = Adw.PreferencesGroup(title="Schreib-Erinnerung")
        self.reminder_switch = Adw.SwitchRow(
            title="Tägliche Erinnerung", active=bool(config.get("reminder", "enabled"))
        )
        self.reminder_switch.connect(
            "notify::active", lambda row, _: config.set("reminder", "enabled", row.get_active())
        )
        group.add(self.reminder_switch)
        self.time_row = Adw.EntryRow(title="Uhrzeit (HH:MM)")
        self.time_row.set_text(config.get("reminder", "time") or "21:00")
        self.time_row.connect("changed", lambda row: config.set("reminder", "time", row.get_text()))
        group.add(self.time_row)
        page.add(group)

        group = Adw.PreferencesGroup(title="Sperre")
        lock = LockManager(config)
        self.lock_switch = Adw.SwitchRow(title="PIN-Sperre", active=lock.enabled)
        self.lock_switch.connect("notify::active", self._on_lock_toggle, lock)
        group.add(self.lock_switch)
        adjustment = Gtk.Adjustment(
            value=config.get("lock", "auto_lock_minutes") or 5, lower=1, upper=120, step_increment=1
        )
        self.autolock_row = Adw.SpinRow(title="Auto-Lock nach Minuten", adjustment=adjustment)
        self.autolock_row.connect(
            "notify::value",
            lambda row, _: config.set("lock", "auto_lock_minutes", int(row.get_value())),
        )
        group.add(self.autolock_row)
        pin_row = Adw.ActionRow(title="PIN ändern")
        pin_btn = Gtk.Button(label="Ändern", valign=Gtk.Align.CENTER)
        pin_btn.connect("clicked", self._on_change_pin, lock)
        pin_row.add_suffix(pin_btn)
        group.add(pin_row)
        page.add(group)

        group = Adw.PreferencesGroup(title="Darstellung")
        self.theme_row = Adw.ComboRow(title="Design", model=Gtk.StringList.new(THEMES))
        current_theme = config.get("appearance", "theme") or "system"
        self.theme_row.set_selected({"system": 0, "light": 1, "dark": 2}.get(current_theme, 0))
        self.theme_row.connect("notify::selected", lambda row, _: config.set(
            "appearance", "theme", ["system", "light", "dark"][row.get_selected()]))
        group.add(self.theme_row)
        self.accent_row = Adw.ComboRow(title="Akzentfarbe", model=Gtk.StringList.new(ACCENTS))
        accent = config.get("appearance", "accent") or "blau"
        self.accent_row.set_selected(max(0, ACCENTS.index(accent)))
        self.accent_row.connect("notify::selected", lambda row, _: config.set(
            "appearance", "accent", ACCENTS[row.get_selected()]))
        group.add(self.accent_row)
        self.font_row = Adw.EntryRow(title="Schriftart")
        self.font_row.set_text(config.get("appearance", "font") or "Cantarell 11")
        self.font_row.connect(
            "changed", lambda row: config.set("appearance", "font", row.get_text())
        )
        group.add(self.font_row)
        page.add(group)

        self.add(page)

    def _save_webdav(self, *_args) -> None:
        self.config.set("webdav", "url", self.url_row.get_text().strip().rstrip("/"))
        self.config.set("webdav", "username", self.user_row.get_text().strip())
        self.config.set("webdav", "remote_path", self.remote_row.get_text().strip().strip("/"))
        if self.on_webdav_changed:
            self.on_webdav_changed()

    def _on_set_password(self, *_args) -> None:
        dialog = PasswordDialog(self.window, "App-Passwort", self._store_password)
        dialog.present()

    def _store_password(self, password: str) -> None:
        self.config.set_webdav_password(password)

    def _on_change_path(self, btn: Gtk.Button, row: Adw.ActionRow) -> None:
        dialog = Gtk.FileDialog()

        def done(d, res):
            self._select_folder_done(d, res, row)

        dialog.select_folder(self.window, None, done)

    def _select_folder_done(self, dialog: Gtk.FileDialog, result, row: Adw.ActionRow) -> None:
        try:
            folder = dialog.select_folder_finish(result)
        except Exception:
            return
        if folder is None:
            return
        path = folder.get_path()
        self.config.set("journal_path", path)
        row.set_subtitle(path)

    def _on_lock_toggle(self, row: Adw.SwitchRow, _param, lock: LockManager) -> None:
        if row.get_active():
            pin = getpass.getpass("Neue PIN: ")
            confirm = getpass.getpass("PIN wiederholen: ")
            if pin != confirm or not pin:
                row.set_active(False)
                return
            lock.set_pin(pin)
        else:
            pin = getpass.getpass("PIN zur Deaktivierung: ")
            try:
                lock.disable(pin)
            except ValueError:
                row.set_active(True)

    def _on_change_pin(self, btn: Gtk.Button, lock: LockManager) -> None:
        old = getpass.getpass("Alte PIN: ")
        new = getpass.getpass("Neue PIN: ")
        with contextlib.suppress(ValueError):
            lock.change_pin(old, new)
