with open('C:/xampp/hospital-management-system-main/index.php', 'r', encoding='utf-8') as f:
    for i, line in enumerate(f):
        if 'action ===' in line or 'action == ' in line:
            print(f'Line {i+1}: {line.strip()}')
