from __future__ import annotations

from datetime import date, datetime

import gi

gi.require_version("Gtk", "4.0")
gi.require_version("Adw", "1")
from gi.repository import Adw, Gio, Gtk  # noqa: E402

from ..model import Entry, Location  # noqa: E402
from ..store import JournalStore  # noqa: E402


class EditorPage(Adw.NavigationPage):
    def __init__(self, window, day: date, store: JournalStore, entry: Entry | None = None):
        self.window = window
        self.day = day
        self.store = store
        self.entries = store.entries_for_day(day)
        self.entry = entry or (self.entries[0] if self.entries else None)
        super().__init__(title=day.strftime("%d.%m.%Y"))

        outer = Gtk.Box(orientation=Gtk.Orientation.VERTICAL)
        self.set_child(outer)

        if len(self.entries) > 1:
            picker_box = Gtk.Box(spacing=6)
            picker_box.set_margin_top(6)
            picker_box.set_margin_start(12)
            picker_box.set_margin_end(12)
            picker_box.append(Gtk.Label(label="Eintrag:"))
            self.picker = Gtk.DropDown.new_from_strings(
                [e.time.strftime("%H:%M") if e.time else "Haupteintrag" for e in self.entries]
            )
            self.picker.set_selected(0)
            self.picker.connect("notify::selected", self._on_picker)
            picker_box.append(self.picker)
            outer.append(picker_box)

        self.heading_entry = Gtk.Entry(placeholder_text="Überschrift (optional)")
        self.heading_entry.set_margin_top(6)
        self.heading_entry.set_margin_start(12)
        self.heading_entry.set_margin_end(12)
        outer.append(self.heading_entry)

        scrolled = Gtk.ScrolledWindow()
        scrolled.set_vexpand(True)
        scrolled.set_margin_top(6)
        scrolled.set_margin_start(12)
        scrolled.set_margin_end(12)
        scrolled.set_margin_bottom(6)
        self.buffer = Gtk.TextBuffer()
        self.textview = Gtk.TextView(buffer=self.buffer)
        self.textview.add_css_class("monospace")
        self.textview.set_wrap_mode(Gtk.WrapMode.WORD_CHAR)
        self.textview.set_top_margin(8)
        self.textview.set_bottom_margin(8)
        self.textview.set_left_margin(8)
        self.textview.set_right_margin(8)
        self.textview.add_css_class("card")
        scrolled.set_child(self.textview)
        outer.append(scrolled)

        outer.append(self._build_meta_bar())
        outer.append(self._build_actions_bar())

        self.heading_entry.connect("changed", lambda *_: self._autosave())
        self.buffer.connect("changed", lambda *_: self._autosave())
        self._load_entry()

    def _build_meta_bar(self) -> Gtk.Widget:
        bar = Gtk.Box(spacing=8)
        bar.set_margin_start(12)
        bar.set_margin_end(12)
        bar.set_margin_bottom(6)

        bar.append(Gtk.Label(label="Bewertung:"))
        self.rating_buttons: list[Gtk.ToggleButton] = []
        for value in range(1, 6):
            btn = Gtk.ToggleButton(label="★")
            btn.set_valign(Gtk.Align.CENTER)
            btn.connect("toggled", self._on_rating, value)
            self.rating_buttons.append(btn)
            bar.append(btn)

        self.tags_entry = Gtk.Entry(placeholder_text="Tags (kommagetrennt)")
        self.tags_entry.set_hexpand(True)
        self.tags_entry.connect("changed", lambda *_: self._autosave())
        bar.append(self.tags_entry)

        self.location_entry = Gtk.Entry(placeholder_text="Ort (optional)")
        self.location_entry.set_hexpand(True)
        self.location_entry.connect("changed", lambda *_: self._autosave())
        bar.append(self.location_entry)
        return bar

    def _build_actions_bar(self) -> Gtk.Widget:
        bar = Gtk.Box(spacing=8)
        bar.set_margin_start(12)
        bar.set_margin_end(12)
        bar.set_margin_bottom(12)

        self.template_btn = Gtk.MenuButton(label="Vorlage")
        self.template_menu = Gio.Menu()
        for name in self.store.list_templates():
            item = Gio.MenuItem.new(name, f"app.insert-template::{name}")
            self.template_menu.append_item(item)
        self.template_btn.set_menu_model(self.template_menu)
        bar.append(self.template_btn)

        attach_btn = Gtk.Button(label="Datei anhängen")
        attach_btn.connect("clicked", self._on_attach)
        bar.append(attach_btn)

        self.attach_label = Gtk.Label(label="")
        self.attach_label.add_css_class("dim-label")
        self.attach_label.add_css_class("caption")
        self.attach_label.set_hexpand(True)
        self.attach_label.set_halign(Gtk.Align.START)
        bar.append(self.attach_label)

        delete_btn = Gtk.Button(label="Löschen")
        delete_btn.add_css_class("destructive-action")
        delete_btn.connect("clicked", self._on_delete)
        bar.append(delete_btn)

        save_btn = Gtk.Button(label="Speichern")
        save_btn.add_css_class("suggested-action")
        save_btn.connect("clicked", lambda *_: self.save())
        bar.append(save_btn)
        return bar

    def _on_picker(self, *_args) -> None:
        index = self.picker.get_selected()
        if 0 <= index < len(self.entries):
            self.entry = self.entries[index]
            self._load_entry()

    def _on_rating(self, btn: Gtk.ToggleButton, value: int) -> None:
        if btn.get_active():
            for other in self.rating_buttons:
                if other is not btn:
                    other.set_active(False)
            if self.entry is not None:
                self.entry.rating = value
            self._autosave()
        elif self.entry is not None and self.entry.rating == value:
            btn.set_active(True)

    def _on_attach(self, *_args) -> None:
        dialog = Gtk.FileDialog()
        dialog.open(self.window, None, self._on_attach_done)

    def _on_attach_done(self, dialog: Gtk.FileDialog, result) -> None:
        try:
            file = dialog.open_finish(result)
        except Exception:
            return
        if file is None:
            return
        rel = self.store.add_attachment(self.day, file.get_path())
        if self.entry is not None and rel not in self.entry.attachments:
            self.entry.attachments.append(rel)
        self.save()
        self.window.show_toast(f"Angehängt: {rel}")

    def _on_delete(self, *_args) -> None:
        if self.entry is None:
            return
        self.store.delete_entry(self.entry.date, self.entry.time)
        self.window.show_toast("Eintrag gelöscht")
        self.window.pop_page()
        self.window.refresh()

    def _load_entry(self) -> None:
        if self.entry is None:
            return
        self.heading_entry.set_text(self.entry.heading)
        self.buffer.set_text(self.entry.body)
        self.tags_entry.set_text(", ".join(self.entry.tags))
        self.location_entry.set_text(self.entry.location.name if self.entry.location else "")
        for index, btn in enumerate(self.rating_buttons, start=1):
            btn.set_active(self.entry.rating == index)
        self.attach_label.set_label(
            ", ".join(self.entry.attachments) if self.entry.attachments else ""
        )

    def _autosave(self) -> None:
        self.save()

    def save(self) -> None:
        now = datetime.now().time().replace(second=0, microsecond=0)
        if self.entry is None:
            self.entry = Entry(date=self.day, time=now if self.entries else None)
        entry = self.entry
        entry.heading = self.heading_entry.get_text().strip()
        start, end = self.buffer.get_bounds()
        entry.body = self.buffer.get_text(start, end, False)
        entry.tags = [t.strip() for t in self.tags_entry.get_text().split(",") if t.strip()]
        location_name = self.location_entry.get_text().strip()
        if location_name:
            old = entry.location
            entry.location = Location(
                name=location_name,
                lat=old.lat if old else None,
                lon=old.lon if old else None,
            )
        else:
            entry.location = None
        self.store.save_entry(entry)
        self.window.refresh()

    def insert_template(self, name: str) -> None:
        try:
            text = self.store.get_template(name)
        except FileNotFoundError:
            return
        start, end = self.buffer.get_bounds()
        current = self.buffer.get_text(start, end, False)
        if current.strip():
            merged = (current.rstrip("\n") + "\n\n" + text).strip("\n") + "\n"
            self.buffer.set_text(merged)
        else:
            self.buffer.set_text(text)
        self.save()
