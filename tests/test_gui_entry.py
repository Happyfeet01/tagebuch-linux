import importlib.util
import subprocess
import sys

import pytest


@pytest.mark.skipif(
    importlib.util.find_spec("gi") is not None,
    reason="gi ist installiert – der Fehlerpfad ohne gi ist hier nicht testbar",
)
def test_gui_entry_without_gi_shows_friendly_hint():
    result = subprocess.run(
        [sys.executable, "-c", "from tagebuch.app import main; raise SystemExit(main())"],
        capture_output=True,
        text=True,
        timeout=60,
    )
    assert result.returncode == 1
    assert "PyGObject" in result.stderr
    assert "apt install python3-gi" in result.stderr
    assert "--system-site-packages" in result.stderr
    assert "Traceback" not in result.stderr


def test_gui_hint_mentions_adw_typelib():
    from tagebuch.app import GUI_HINT

    assert "gir1.2-adw-1" in GUI_HINT
