from pathlib import Path

root = Path(r"C:\xampp\htdocs\Telecare 2.0")
replacements = {
    'â€”': '—',
    'â€“': '–',
    'â€œ': '“',
    'â€': '”',
    'â€˜': '‘',
    'â€™': '’',
    'Â·': '·',
    'â€¢': '•',
    'Â€': '€',
    'Â£': '£',
    'Â₱': '₱',
    'âœ“': '✓',
    'âœ•': '✕',
    'â†’': '→',
    'â†“': '←',
    'âˆ’': '−',
    'â”€': '─',
    'â•': '•',
    'â€¯': ' ',
    'â€‹': '',
    'Ã¢â‚¬': '“',
    'Ã¢â€šÂ¬': '€',
    'Â“': '“',
    'Â”': '”',
    'Â‘': '‘',
    'Â’': '’',
    'âœ…': '✓',
    'âœ—': '✕',
    'â³': '›',
    'âš ï¸': '⚠️',
    'âš': '⚠',
    'ðŸ”': '🔐',
    'ðŸ“§': '📩',
    'ðŸ“¬': '📬',
    'ðŸ”': '📩',
    'ðŸ”—': '📧',
    'ðŸ“‹': '📋',
    'ðŸ”’': '📄',
    'ðŸ”‘': '🔑',
    'ðŸ¥': '🏥',
    'ðŸ§ª': '🧪',
    'ðŸ’Š': '💊',
    'ðŸ“„': '📄',
    'ðŸ”': '🔐',
}

changed = []
for path in root.rglob('*.php'):
    try:
        text = path.read_text(encoding='utf-8')
    except UnicodeDecodeError:
        try:
            text = path.read_text(encoding='latin-1')
        except Exception:
            continue
    original = text
    for old, new in replacements.items():
        text = text.replace(old, new)
    if text != original:
        path.write_text(text, encoding='utf-8')
        changed.append(str(path.relative_to(root)))

print(f'Updated {len(changed)} files')
for file in changed[:50]:
    print(file)
