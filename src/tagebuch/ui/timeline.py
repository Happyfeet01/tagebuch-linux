from __future__ import annotations

import gi

gi.require_version("Gtk", "4.0")
gi.require_version("Adw", "1")
from gi.repository import Adw, Gtk  # noqa: E402

from ..exchange import stars  # noqa: E402
from ..model import Entry  # noqa: E402


def entry_row(entry: Entry, on_open) -> Gtk.ListBoxRow:
    row = Gtk.ListBoxRow()
    box = Gtk.Box(orientation=Gtk.Orientation.VERTICAL, spacing=2)
    box.set_margin_top(8)
    box.set_margin_bottom(8)
    box.set_margin_start(12)
    box.set_margin_end(12)

    stamp = entry.date.strftime("%d.%m.%Y")
    if entry.time:
        stamp += f" {entry.time.strftime('%H:%M')}"
    top = Gtk.Box(spacing=8)
    date_label = Gtk.Label(label=stamp)
    date_label.set_halign(Gtk.Align.START)
    date_label.set_hexpand(True)
    date_label.add_css_class("heading")
    top.append(date_label)
    if entry.rating is not None:
        rating_label = Gtk.Label(label=stars(entry.rating))
        rating_label.add_css_class("stars")
        top.append(rating_label)
    box.append(top)

    if entry.heading:
        title = entry.heading
    elif entry.body.strip():
        title = entry.body.strip().splitlines()[0]
    else:
        title = "(ohne Text)"
    title_label = Gtk.Label(label=title[:80])
    title_label.set_halign(Gtk.Align.START)
    title_label.set_ellipsize(3)
    box.append(title_label)

    if entry.tags:
        tags_label = Gtk.Label(label=" ".join("#" + t for t in entry.tags))
        tags_label.set_halign(Gtk.Align.START)
        tags_label.add_css_class("dim-label")
        tags_label.add_css_class("caption")
        box.append(tags_label)

    row.set_child(box)
    row.entry = entry
    row.connect("activated", lambda r: on_open(r.entry))
    return row


class TimelineView(Gtk.Box):
    def __init__(self, on_open_entry, title: str = "Timeline"):
        super().__init__(orientation=Gtk.Orientation.VERTICAL, spacing=0)
        self.on_open_entry = on_open_entry
        header = Gtk.Label(label=title)
        header.add_css_class("title-2")
        header.set_margin_top(12)
        header.set_margin_bottom(6)
        header.set_margin_start(12)
        self.append(header)

        scrolled = Gtk.ScrolledWindow()
        scrolled.set_vexpand(True)
        scrolled.set_policy(Gtk.PolicyType.NEVER, Gtk.PolicyType.AUTOMATIC)
        self.listbox = Gtk.ListBox()
        self.listbox.set_selection_mode(Gtk.SelectionMode.NONE)
        self.listbox.add_css_class("boxed-list")
        self.listbox.set_margin_start(12)
        self.listbox.set_margin_end(12)
        self.listbox.set_margin_bottom(12)
        scrolled.set_child(self.listbox)
        self.append(scrolled)

    def refresh(self, entries: list[Entry]) -> None:
        while True:
            row = self.listbox.get_row_at_index(0)
            if row is None:
                break
            self.listbox.remove(row)
        for entry in entries:
            self.listbox.append(entry_row(entry, self.on_open_entry))
        if not entries:
            empty = Adw.StatusPage(
                title="Noch keine Einträge",
                description="Wähle einen Tag im Kalender oder lege oben einen neuen Eintrag an.",
            )
            self.listbox.append(Gtk.ListBoxRow())
            self.listbox.get_row_at_index(0).set_child(empty)
