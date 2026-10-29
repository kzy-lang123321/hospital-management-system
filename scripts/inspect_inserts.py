import re

with open('index.php', 'r', encoding='utf-8') as f:
    content = f.read()

matches = re.findall(r'INSERT\s+INTO\s+[`]?([a-zA-Z0-9_]+)', content, re.IGNORECASE)
print("Unique tables in INSERT statements in index.php:")
print(sorted(list(set(matches))))
