from pathlib import Path

root = Path(r'C:\xampp\htdocs\Telecare 2.0')

replacements = {
    'â€”': '—',
    'â€“': '–',
    'â€œ': '“',
    'â€': '”',
    'â€˜': '‘',
    'â€™': '’',
    'â€¢': '•',
    'â€': '–',
    'â€ž': '„',
    'â€º': '»',
    'â€¹': '«',
    'Â·': '·',
    'Â€': '€',
    'Â£': '£',
    'Â₱': '₱',
    'âœ“': '✓',
    'âœ•': '✕',
    'âœ…': '…',
    'â†’': '→',
    'â†“': '←',
    'â†': '←',
    'âš ï¸': '⚠️',
    'â³': '▸',
    'âŒ': '✘',
    'ðŸ”': '✉️',
    'ðŸ“§': '📩',
    'ðŸ“¬': '📬',
    'ðŸ”': '📧',
    'ðŸ”—': '—',
    'ðŸ§ª': '🧾',
    'ðŸ’Š': '🧾',
    'ðŸ“„': '📄',
    'ðŸ”‘': '🛠',
    'ðŸ“‹': '📘',
    'ðŸ”’': '📄',
    'â”€': '—',
    'â•': '—',
    'â€¯': ' ',
    'â€‹': ' ',
    'âˆ’': '−',
    'â™': '™',
    'âœ—': '✕',
    'Ã': '',
    'Â': '',
}

updated_files = 0
for path in root.rglob('*.php'):
    try:
        text = path.read_text(encoding='utf-8')
    except Exception:
        continue
    original = text
    for bad, good in replacements.items():
        text = text.replace(bad, good)
    if text != original:
        path.write_text(text, encoding='utf-8')
        updated_files += 1

print(f'updated_php_files={updated_files}')
