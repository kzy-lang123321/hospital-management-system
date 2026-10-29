import os, glob, re

views_dir = 'C:/xampp/hospital-management-system-main/views'
files = glob.glob(views_dir + '/**/*.php', recursive=True)
files.append('C:/xampp/hospital-management-system-main/index.php')

targets = ['patients', 'appointments', 'bills', 'users', 'prescriptions', 'departments', 'encounters', 'lab_orders', 'pharmacy_sales']

for f in files:
    with open(f, 'r', encoding='utf-8', errors='ignore') as fp:
        content = fp.read()
    matches = re.findall(r'FROM\s+[`"]?([a-zA-Z_]+)[`"]?', content, re.IGNORECASE)
    found = set(m.lower() for m in matches if m.lower() in targets)
    if found:
        rel = os.path.relpath(f, 'C:/xampp/hospital-management-system-main')
        print(f'{rel}: {list(found)}')
