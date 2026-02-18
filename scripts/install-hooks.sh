#!/bin/sh

# Install git pre-commit hook for quality checks
HOOK_DIR="$(git rev-parse --show-toplevel)/.git/hooks"

cat > "${HOOK_DIR}/pre-commit" << 'HOOK'
#!/bin/sh

echo "Running quality checks..."
make quality

if [ $? -ne 0 ]; then
    echo ""
    echo "Quality checks failed. Commit aborted."
    exit 1
fi
HOOK

chmod +x "${HOOK_DIR}/pre-commit"
echo "Pre-commit hook installed."
