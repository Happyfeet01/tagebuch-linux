from __future__ import annotations

import calendar
from datetime import date

import gi

gi.require_version("Gtk", "4.0")
from gi.repository import Gtk  # noqa: E402

WEEKDAYS = ["Mo", "Di", "Mi", "Do", "Fr", "Sa", "So"]


class CalendarView(Gtk.Box):
    def __init__(self, on_day_selected=None):
        super().__init__(orientation=Gtk.Orientation.VERTICAL, spacing=6)
        self.on_day_selected = on_day_selected
        today = date.today()
        self.year = today.year
        self.month = today.month

        header = Gtk.Box(spacing=6)
        self.prev_btn = Gtk.Button.new_from_icon_name("go-previous-symbolic")
        self.prev_btn.connect("clicked", lambda *_: self._shift_month(-1))
        self.next_btn = Gtk.Button.new_from_icon_name("go-next-symbolic")
        self.next_btn.connect("clicked", lambda *_: self._shift_month(1))
        self.today_btn = Gtk.Button(label="Heute")
        self.today_btn.connect("clicked", lambda *_: self.show_today())
        self.month_label = Gtk.Label()
        self.month_label.set_hexpand(True)
        self.month_label.set_halign(Gtk.Align.CENTER)
        header.append(self.prev_btn)
        header.append(self.month_label)
        header.append(self.today_btn)
        header.append(self.next_btn)
        self.append(header)

        self.grid = Gtk.Grid(column_spacing=2, row_spacing=2)
        for col, name in enumerate(WEEKDAYS):
            label = Gtk.Label(label=name)
            label.add_css_class("dim-label")
            label.add_css_class("caption")
            self.grid.attach(label, col, 0, 1, 1)
        self.append(self.grid)
        self.day_buttons: dict[date, Gtk.Button] = {}
        self._marks: dict[date, int | None] = {}

    def _shift_month(self, delta: int) -> None:
        self.month += delta
        while self.month < 1:
            self.month += 12
            self.year -= 1
        while self.month > 12:
            self.month -= 12
            self.year += 1
        self.refresh()

    def show_today(self) -> None:
        today = date.today()
        self.year, self.month = today.year, today.month
        self.refresh()

    def set_marks(self, marks: dict[date, int | None]) -> None:
        self._marks = marks
        self.refresh()

    def refresh(self) -> None:
        for child in list(self.day_buttons.values()):
            self.grid.remove(child)
        self.day_buttons.clear()
        self.month_label.set_label(f"{calendar.month_name[self.month]} {self.year}")
        weeks = calendar.monthcalendar(self.year, self.month)
        for row, week in enumerate(weeks, start=1):
            for col, day in enumerate(week):
                if day == 0:
                    self.grid.attach(Gtk.Label(), col, row, 1, 1)
                    continue
                day_date = date(self.year, self.month, day)
                btn = Gtk.Button(label=str(day))
                btn.set_has_frame(False)
                if day_date == date.today():
                    btn.add_css_class("today")
                if day_date in self._marks:
                    btn.add_css_class("has-entry")
                    rating = self._marks[day_date]
                    if rating is not None:
                        btn.add_css_class(f"rating-{rating}")
                btn.connect("clicked", self._on_day, day_date)
                self.grid.attach(btn, col, row, 1, 1)
                self.day_buttons[day_date] = btn

    def _on_day(self, btn: Gtk.Button, day: date) -> None:
        if self.on_day_selected:
            self.on_day_selected(day)
