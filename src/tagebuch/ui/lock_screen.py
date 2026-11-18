from __future__ import annotations

import gi

gi.require_version("Gtk", "4.0")
gi.require_version("Adw", "1")
from gi.repository import Gtk  # noqa: E402

from ..lock import LockManager  # noqa: E402


class LockScreen(Gtk.Window):
    def __init__(self, window: Gtk.Window, lock: LockManager, on_unlock):
        super().__init__(modal=True, transient_for=window, decorated=False)
        self.lock = lock
        self.on_unlock = on_unlock
        self.set_default_size(360, 220)
        self.set_title("Tagebuch gesperrt")

        box = Gtk.Box(orientation=Gtk.Orientation.VERTICAL, spacing=12)
        box.set_margin_top(24)
        box.set_margin_bottom(24)
        box.set_margin_start(24)
        box.set_margin_end(24)
        box.set_halign(Gtk.Align.CENTER)
        box.set_valign(Gtk.Align.CENTER)

        title = Gtk.Label(label="Tagebuch ist gesperrt")
        title.add_css_class("title-2")
        box.append(title)

        self.pin_entry = Gtk.PasswordEntry()
        self.pin_entry.set_placeholder_text("PIN eingeben")
        self.pin_entry.set_show_peek_button(True)
        self.pin_entry.connect("activate", lambda *_: self._try_unlock())
        box.append(self.pin_entry)

        self.error_label = Gtk.Label(label="")
        self.error_label.add_css_class("error")
        box.append(self.error_label)

        btn = Gtk.Button(label="Entsperren")
        btn.add_css_class("suggested-action")
        btn.connect("clicked", lambda *_: self._try_unlock())
        box.append(btn)

        self.set_child(box)
        self.pin_entry.grab_focus()

    def _try_unlock(self) -> None:
        if self.lock.verify(self.pin_entry.get_text()):
            self.on_unlock()
            self.close()
        else:
            self.error_label.set_label("PIN falsch")
            self.pin_entry.set_text("")
            self.pin_entry.grab_focus()
