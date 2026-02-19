#!/bin/sh

# Install git pre-commit hook for quality checks
HOOK_DIR="$(git rev-parse --show-toplevel)/.git/hooks"

cat > "${HOOK_DIR}/pre-commit" << 'HOOK'
#!/bin/sh

echo ""
echo "========================================"
echo "  Pre-commit quality checks"
echo "========================================"
echo ""

echo "[1/5] Lint (phpcbf)..."
make lint
if [ $? -ne 0 ]; then
    echo "FAILED: Lint errors found."
    exit 1
fi

# Re-stage any files auto-fixed by phpcbf
git add -u

echo "[2/5] Static analysis (PHPStan)..."
make static
if [ $? -ne 0 ]; then
    echo "FAILED: PHPStan errors found."
    exit 1
fi

echo "[3/5] Refactor (Rector)..."
make refactor
if [ $? -ne 0 ]; then
    echo "FAILED: Rector errors found."
    exit 1
fi

# Re-stage any files auto-fixed by Rector
CHANGED=$(git diff --name-only)
if [ -n "$CHANGED" ]; then
    echo "  Rector applied fixes, re-staging..."
    git add -u
fi

echo "[4/5] PHP tests..."
make test
if [ $? -ne 0 ]; then
    echo "FAILED: PHP tests failed."
    exit 1
fi

echo "[5/5] JS tests (Vitest)..."
make test-js
if [ $? -ne 0 ]; then
    echo "FAILED: JS tests failed."
    exit 1
fi

echo ""
echo "========================================"
echo "  All checks passed!"
echo "========================================"
echo ""
HOOK

chmod +x "${HOOK_DIR}/pre-commit"
echo "Pre-commit hook installed."
