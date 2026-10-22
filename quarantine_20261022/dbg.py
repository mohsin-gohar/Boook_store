import sys

f = 'tests/Feature/BookstoreApiTest.php'
with open(f, 'rb') as fh:
    b = fh.read()
lines = b.split(b'\n')

print('Total lines:', len(lines))

# Step 1: Remove duplicate '// Orders' at line 304 (keep line 305)
assert lines[303] == b'    // ---------------------------------------------------------------- Orders'
assert lines[304] == b'    // ---------------------------------------------------------------- Orders'
del lines[303]  # removes line 304 (0-indexed 303)

# After deletion, all indices >= 303 shift down by 1.
# Original line 306 (index 305) is now at index 304.
# Original line 342 (index 341) is now at index 340.
# Remove indices 304-340 (0-based) = old lines 306-342
print('Line at index 304 (should be checkout test):', repr(lines[304]))
print('Line at index 340 (should be blank after fails test):', repr(lines[340]))
print('Line at index 341 (should be requires_shipping_address):', repr(lines[341]))

del lines[304:341]  # removes indices 304 through 340 inclusive

print('After removal, total lines:', len(lines))
print('Line around edit:')
for i in range(298, 312):
    print(i+1, repr(lines[i]))

# Step 3: Check if file ends with class closing brace
print('Last 5 lines:')
for i in range(len(lines)-5, len(lines)):
    print(i+1, repr(lines[i]))

last_non_blank = None
for i in range(len(lines)-1, -1, -1):
    if lines[i].strip():
        last_non_blank = i
        break
print('Last non-blank line:', last_non_blank+1, repr(lines[last_non_blank]))

if lines[last_non_blank].rstrip() != b'}':
    lines.append(b'')
    lines.append(b'}')
    print('Added closing brace')

with open(f, 'wb') as fh:
    fh.write(b'\n'.join(lines))

print('Done, final lines:', len(lines))

last_non_blank = None
for i in range(len(lines)-1, -1, -1):
    if lines[i].strip():
        last_non_blank = i
        break
print('Last non-blank line:', last_non_blank+1, repr(lines[last_non_blank]))

if lines[last_non_blank].rstrip() != b'}':
    lines.append(b'')
    lines.append(b'}')
    print('Added closing brace')

with open(f, 'wb') as fh:
    fh.write(b'\n'.join(lines))

print('Done, final lines:', len(lines))
