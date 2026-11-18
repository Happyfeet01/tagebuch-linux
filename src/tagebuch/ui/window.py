from __future__ import annotations

from datetime import date, timedelta

import gi

gi.require_version("Gtk", "4.0")
gi.require_version("Adw", "1")
from gi.repository import Adw, Gio, Gtk  # noqa: E402

from ..model import Entry  # noqa: E402
from ..search import SearchFilter, search_entries  # noqa: E402
from ..stats import on_this_day  # noqa: E402
from ..store import JournalStore  # noqa: E402
from .calendar_view import CalendarView  # noqa: E402
from .editor import EditorPage  # noqa: E402
from .timeline import TimelineView  # noqa: E402


class MainWindow(Adw.ApplicationWindow):
    def __init__(self, app, store: JournalStore):
        super().__init__(application=app)
        self.app = app
        self.store = store
        self.set_title("Tagebuch")
        self.set_default_size(1000, 680)

        self.toast_overlay = Adw.ToastOverlay()
        toolbar = Adw.ToolbarView()
        self.toast_overlay.set_child(toolbar)
        self.set_content(self.toast_overlay)

        header = Adw.HeaderBar()
        toolbar.add_top_bar(header)

        new_btn = Gtk.Button(label="Neu")
        new_btn.add_css_class("suggested-action")
        new_btn.connect("clicked", lambda *_: self.open_day(date.today(), new=True))
        header.pack_start(new_btn)

        self.search_entry = Gtk.SearchEntry()
        self.search_entry.set_placeholder_text("Suchen …")
        self.search_entry.connect("search-changed", self._on_search)
        self.search_entry.set_hexpand(True)
        self.search_entry.set_max_width_chars(28)
        header.set_title_widget(self.search_entry)

        self.sync_spinner = Gtk.Spinner()
        self.sync_btn = Gtk.Button()
        self.sync_btn.set_child(self.sync_spinner)
        self.sync_btn.set_tooltip_text("Mit Nextcloud synchronisieren")
        self.sync_spinner.set_spinning(False)
        self.sync_btn.connect("clicked", lambda *_: app.sync_now())
        header.pack_end(self.sync_btn)

        menu = Gio.Menu()
        menu.append("Einstellungen", "app.settings")
        menu.append("Exportieren …", "app.export")
        menu.append("Importieren …", "app.import")
        menu.append("Jetzt sperren", "app.lock")
        menu.append("Über Tagebuch", "app.about")
        menu_btn = Gtk.MenuButton(icon_name="open-menu-symbolic", menu_model=menu)
        header.pack_end(menu_btn)

        split = Adw.OverlaySplitView()
        split.set_min_sidebar_width(280)
        split.set_max_sidebar_width(320)
        split.set_sidebar_position(Gtk.PackType.START)
        toolbar.set_content(split)

        sidebar = Gtk.Box(orientation=Gtk.Orientation.VERTICAL, spacing=12)
        sidebar.set_margin_top(12)
        sidebar.set_margin_start(12)
        sidebar.set_margin_end(12)
        sidebar.set_margin_bottom(12)
        self.calendar = CalendarView(on_day_selected=self.open_day)
        sidebar.append(self.calendar)

        otd_label = Gtk.Label(label="An diesem Tag")
        otd_label.add_css_class("heading")
        otd_label.set_halign(Gtk.Align.START)
        sidebar.append(otd_label)
        self.otd_list = Gtk.ListBox()
        self.otd_list.set_selection_mode(Gtk.SelectionMode.NONE)
        self.otd_list.add_css_class("boxed-list")
        self.otd_list.connect("row-activated", self._on_otd_row)
        sidebar.append(self.otd_list)
        split.set_sidebar(sidebar)

        self.nav = Adw.NavigationView()
        self.timeline = TimelineView(on_open_entry=self.open_entry)
        page = Adw.NavigationPage(child=self.timeline, title="Tagebuch")
        self.nav.add(page)
        split.set_content(self.nav)

        self.refresh()

    def refresh(self) -> None:
        entries = self.store.list_entries()
        marks: dict[date, int | None] = {}
        for entry in entries:
            marks[entry.date] = entry.rating
        self.calendar.set_marks(marks)
        self.timeline.refresh(entries)
        while (row := self.otd_list.get_row_at_index(0)) is not None:
            self.otd_list.remove(row)
        for entry in on_this_day(entries):
            snippet = entry.heading
            if not snippet and entry.body.strip():
                snippet = entry.body.strip().splitlines()[0][:40]
            label = Gtk.Label(label=f"{entry.date.year}  {snippet}")
            label.set_halign(Gtk.Align.START)
            label.set_margin_start(8)
            label.set_margin_top(6)
            label.set_margin_bottom(6)
            row = Gtk.ListBoxRow()
            row.set_child(label)
            row.entry = entry
            self.otd_list.append(row)

    def open_day(self, day: date, new: bool = False) -> None:
        entries = self.store.entries_for_day(day)
        if new or not entries:
            page = EditorPage(self, day, self.store, entry=None)
        else:
            page = EditorPage(self, day, self.store, entry=entries[0])
        self.nav.push(page)

    def open_entry(self, entry: Entry) -> None:
        page = EditorPage(self, entry.date, self.store, entry=entry)
        self.nav.push(page)

    def pop_page(self) -> None:
        self.nav.pop()

    def _on_search(self, entry: Gtk.SearchEntry) -> None:
        query = entry.get_text().strip()
        if not query:
            if self.nav.get_visible_page() is not self.nav.get_navigation_stack().get_item(0):
                self.nav.pop_to_page(self.nav.get_navigation_stack().get_item(0))
            return
        results = search_entries(self.store, SearchFilter(query=query))
        view = TimelineView(on_open_entry=self.open_entry, title=f"Suche: {query}")
        view.refresh(results)
        page = Adw.NavigationPage(child=view, title=f"Suche: {query}")
        if self.nav.get_visible_page().get_title().startswith("Suche:"):
            self.nav.replace([page])
        else:
            self.nav.push(page)

    def _on_otd_row(self, listbox: Gtk.ListBox, row: Gtk.ListBoxRow) -> None:
        self.open_entry(row.entry)

    def show_toast(self, message: str, timeout: int = 3) -> None:
        self.toast_overlay.add_toast(Adw.Toast(title=message, timeout=timeout))

    def set_syncing(self, syncing: bool) -> None:
        self.sync_spinner.set_spinning(syncing)
        self.sync_btn.set_sensitive(not syncing)

    def days_window(self, days: int) -> list[Entry]:
        cutoff = date.today() - timedelta(days=days)
        return [e for e in self.store.list_entries() if e.date >= cutoff]
