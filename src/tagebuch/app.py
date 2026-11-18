import sys

GUI_HINT = (
    "PyGObject (gi) ist nicht installiert – die GUI braucht die GTK-Systempakete.\n"
    "Debian/Ubuntu:\n"
    "  sudo apt install python3-gi python3-gi-cairo gir1.2-adw-1 gir1.2-gtk-4.0\n"
    "Danach das venv MIT System-Paketen anlegen, damit gi sichtbar ist:\n"
    "  python3 -m venv --system-site-packages .venv\n"
    "  . .venv/bin/activate && pip install .\n"
    "Die CLI (tagebuch ...) funktioniert auch ohne gi."
)


def main() -> int:
    try:
        import gi  # noqa: F401
    except ImportError:
        sys.stderr.write(GUI_HINT + "\n")
        return 1
    from .gui import main as gui_main

    return gui_main()


if __name__ == "__main__":
    raise SystemExit(main())
