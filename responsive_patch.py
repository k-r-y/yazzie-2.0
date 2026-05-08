import os
import re

VIEWS_DIR = 'views'

def process_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    original_content = content

    # 1. Modals
    content = re.sub(r'class="modal-dialog"', 'class="modal-dialog modal-dialog-centered modal-dialog-scrollable"', content)
    content = re.sub(r'class="modal-dialog modal-lg"', 'class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"', content)
    content = re.sub(r'class="modal-dialog modal-xl"', 'class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"', content)
    # Fix potential duplicates if already centered but not scrollable
    content = re.sub(r'class="modal-dialog modal-dialog-centered"', 'class="modal-dialog modal-dialog-centered modal-dialog-scrollable"', content)
    content = re.sub(r'class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-dialog-centered modal-dialog-scrollable"', 'class="modal-dialog modal-dialog-centered modal-dialog-scrollable"', content)

    # 2. Wrap Tables
    # We will do a line-by-line parsing to wrap tables correctly to avoid nesting issues.
    lines = content.split('\n')
    new_lines = []
    in_table = 0
    table_wrapped = []

    for line in lines:
        # Check if table starts
        if '<table' in line and ('class="data-table"' in line or 'class="table"' in line or 'class="table ' in line or 'class="data-table ' in line):
            # Check if it's already wrapped (heuristic: previous line has table-responsive)
            is_wrapped = False
            if len(new_lines) > 0 and 'class="table-responsive"' in new_lines[-1]:
                is_wrapped = True
            
            if not is_wrapped:
                # Get the leading whitespace of the table line for indentation
                leading_space = line[:len(line) - len(line.lstrip())]
                new_lines.append(leading_space + '<div class="table-responsive">')
                table_wrapped.append(True)
            else:
                table_wrapped.append(False)
            in_table += 1
            new_lines.append(line)
        elif '</table' in line and in_table > 0:
            in_table -= 1
            wrapped = table_wrapped.pop()
            new_lines.append(line)
            if wrapped:
                leading_space = line[:len(line) - len(line.lstrip())]
                new_lines.append(leading_space + '</div>')
        else:
            # Check for grid updates in dashboard cards
            # We want to replace `<div class="col-md-4 mb-4">` or `<div class="col-xl-3 col-md-6 mb-4">` 
            # with `<div class="col-12 col-md-6 col-xl-4 mb-3">` for dashboard stat cards
            # Actually, it's easier to use a manual replace for the specific known files later, 
            # but let's try a regex for col-md-4 or col-xl-3 if it contains stat cards.
            # We'll just append the line.
            new_lines.append(line)

    content = '\n'.join(new_lines)

    # 3. Form Grid updates in bookings stepper
    if 'bookings.php' in filepath and 'frontdesk' in filepath:
        content = re.sub(r'class="row"[\s]*>', 'class="row p-3 p-md-5">', content)
        content = re.sub(r'class="col-md-6"', 'class="col-12 col-md-6"', content)

    # Dashboard Grid Updates
    if 'dashboard.php' in filepath:
        content = re.sub(r'class="col-xl-3 col-md-6 mb-4"', 'class="col-12 col-md-6 col-xl-4 mb-3"', content)
        content = re.sub(r'class="col-md-4 mb-4"', 'class="col-12 col-md-6 col-xl-4 mb-3"', content)

    if content != original_content:
        with open(filepath, 'w') as f:
            f.write(content)
        print(f"Updated: {filepath}")

for root, dirs, files in os.walk(VIEWS_DIR):
    for file in files:
        if file.endswith('.php'):
            process_file(os.path.join(root, file))

print("Patch complete.")
