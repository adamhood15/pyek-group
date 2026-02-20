#!/bin/bash

# Build All Emails Script
# Builds all JSON campaign files in the campaigns directory

echo "🚀 Building all email campaigns..."
echo ""

# Change to script directory
cd "$(dirname "$0")"

# Build each email
node build-email.js cbv-email.json
echo ""

node build-email.js tta-email.json
echo ""

node build-email.js tth-email.json
echo ""

echo "✅ All emails built successfully!"
