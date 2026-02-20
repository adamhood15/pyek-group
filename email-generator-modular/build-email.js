#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const Handlebars = require('handlebars');
const juice = require('juice');
const minify = require('html-minifier').minify;
const brandConfig = require('./brand-config');

// Register Handlebars helper for equality check
Handlebars.registerHelper('eq', function(a, b) {
  return a === b;
});

// Register all component partials
const componentsDir = path.join(__dirname, 'templates/components');
console.log('📦 Registering component partials...');

fs.readdirSync(componentsDir).forEach(file => {
  if (file.endsWith('.hbs')) {
    const partialName = path.parse(file).name;
    const partialContent = fs.readFileSync(path.join(componentsDir, file), 'utf8');
    Handlebars.registerPartial(partialName, partialContent);
    console.log(`   ✓ Registered: ${partialName}`);
  }
});

// Register footer partial
const footerPath = path.join(__dirname, 'templates/footer.hbs');
const footerContent = fs.readFileSync(footerPath, 'utf8');
Handlebars.registerPartial('footer', footerContent);
console.log(`   ✓ Registered: footer`);

// Get campaign file from command line
const campaignFile = process.argv[2];

if (!campaignFile) {
  console.error('❌ Error: Please provide a campaign JSON file');
  console.error('Usage: node build-email.js campaign-name.json');
  process.exit(1);
}

const campaignPath = path.join(__dirname, 'campaigns', campaignFile);

if (!fs.existsSync(campaignPath)) {
  console.error(`❌ Error: Campaign file not found: ${campaignPath}`);
  process.exit(1);
}

console.log(`\n🚀 Building email from: ${campaignFile}`);

// Load campaign data
const campaignData = JSON.parse(fs.readFileSync(campaignPath, 'utf8'));

// Validate brand
if (!campaignData.brand || !brandConfig[campaignData.brand]) {
  console.error('❌ Error: Invalid or missing brand in campaign JSON');
  console.error('Valid brands: cbv, tta, tth');
  process.exit(1);
}

// Get brand configuration
const brand = brandConfig[campaignData.brand];
console.log(`   Brand: ${brand.name} (${brand.shortName})`);

// Validate sections array
if (!campaignData.sections || !Array.isArray(campaignData.sections)) {
  console.error('❌ Error: Campaign must have a "sections" array');
  process.exit(1);
}

console.log(`   Sections: ${campaignData.sections.length} components`);

// Load template
const templatePath = path.join(__dirname, 'templates/modular-template.hbs');
const templateSource = fs.readFileSync(templatePath, 'utf8');
const template = Handlebars.compile(templateSource);

// Prepare data for template
const templateData = {
  ...campaignData,
  brandConfig: brand
};

// Generate HTML
console.log('\n🔨 Compiling template...');
let html = template(templateData);

// Inline CSS
console.log('🎨 Inlining CSS...');
html = juice(html, {
  preserveMediaQueries: true,
  preserveFontFaces: true,
  preserveImportant: true,
  removeStyleTags: false
});

// Minify HTML
console.log('📦 Minifying HTML...');
html = minify(html, {
  collapseWhitespace: true,
  removeComments: true,
  minifyCSS: true,
  conservativeCollapse: true,
  preserveLineBreaks: false
});

// Create output directory
const outputDir = path.join(__dirname, 'dist', brand.shortName, '2026');
if (!fs.existsSync(outputDir)) {
  fs.mkdirSync(outputDir, { recursive: true });
}

// Write output file
const outputFileName = path.parse(campaignFile).name + '.html';
const outputPath = path.join(outputDir, outputFileName);
fs.writeFileSync(outputPath, html);

// Build stats
const stats = {
  size: Buffer.byteLength(html, 'utf8'),
  sections: campaignData.sections.length,
  sectionTypes: [...new Set(campaignData.sections.map(s => s.type))]
};

console.log('\n✅ Build complete!');
console.log(`\n📊 Build Stats:`);
console.log(`   File size: ${(stats.size / 1024).toFixed(2)} KB`);
console.log(`   Sections: ${stats.sections}`);
console.log(`   Section types: ${stats.sectionTypes.join(', ')}`);
console.log(`\n📁 Output: ${outputPath}`);
console.log(`\n✨ Ready to upload to ESP!\n`);
