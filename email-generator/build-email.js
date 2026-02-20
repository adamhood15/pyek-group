#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const Handlebars = require('handlebars');
const juice = require('juice');
const minify = require('html-minifier').minify;
const brandConfig = require('./brand-config');

// Configuration
const TEMPLATE_PATH = path.join(__dirname, 'templates', 'promo-template.hbs');
const CAMPAIGNS_DIR = path.join(__dirname, 'campaigns');
const DIST_DIR = path.join(__dirname, 'dist');

// Color codes for terminal output
const colors = {
  reset: '\x1b[0m',
  bright: '\x1b[1m',
  green: '\x1b[32m',
  red: '\x1b[31m',
  yellow: '\x1b[33m',
  cyan: '\x1b[36m'
};

function log(message, color = colors.reset) {
  console.log(`${color}${message}${colors.reset}`);
}

function error(message) {
  log(`✗ ${message}`, colors.red);
}

function success(message) {
  log(`✓ ${message}`, colors.green);
}

function info(message) {
  log(`→ ${message}`, colors.cyan);
}

function validateCampaignData(data, campaignName) {
  const errors = [];

  if (!data.brand) {
    errors.push('Missing brand field (cbv, tta, or tth)');
  } else if (!brandConfig[data.brand]) {
    errors.push(`Invalid brand: ${data.brand}. Must be: cbv, tta, or tth`);
  }

  if (!data.meta || !data.meta.title) {
    errors.push('Missing meta.title');
  }

  if (!data.theme || !data.theme.backgroundColor || !data.theme.textColor) {
    errors.push('Missing theme configuration');
  }

  if (!data.heroImages || !Array.isArray(data.heroImages) || data.heroImages.length === 0) {
    errors.push('heroImages must be an array with at least one image');
  }

  if (data.heroImages) {
    data.heroImages.forEach((img, index) => {
      if (!img.src) errors.push(`heroImages[${index}] missing src`);
      if (!img.alt) errors.push(`heroImages[${index}] missing alt text`);
    });
  }

  if (errors.length > 0) {
    error(`Validation errors in ${campaignName}:`);
    errors.forEach(err => error(`  • ${err}`));
    return false;
  }

  return true;
}

function buildEmail(campaignFile) {
  const startTime = Date.now();

  try {
    // Extract campaign name
    const campaignName = path.basename(campaignFile, '.json');
    info(`Building email: ${campaignName}`);

    // Read campaign JSON
    const campaignPath = path.join(CAMPAIGNS_DIR, campaignFile);
    if (!fs.existsSync(campaignPath)) {
      error(`Campaign file not found: ${campaignFile}`);
      process.exit(1);
    }

    const campaignData = JSON.parse(fs.readFileSync(campaignPath, 'utf8'));

    // Validate campaign data
    if (!validateCampaignData(campaignData, campaignName)) {
      process.exit(1);
    }

    // Merge brand configuration
    const brand = brandConfig[campaignData.brand];
    info(`Using brand: ${brand.name} (${brand.shortName})`);

    const templateData = {
      ...campaignData,
      brandConfig: brand
    };

    // Register Handlebars helpers
    Handlebars.registerHelper('eq', function(a, b) {
      return a === b;
    });

    // Read template
    const templateSource = fs.readFileSync(TEMPLATE_PATH, 'utf8');
    const template = Handlebars.compile(templateSource);

    // Generate HTML
    info('Compiling template...');
    let html = template(templateData);

    // Inline CSS
    info('Inlining CSS...');
    html = juice(html, {
      preserveMediaQueries: true,
      preserveFontFaces: true,
      removeStyleTags: false,
      webResources: {
        relativeTo: __dirname
      }
    });

    // Minify HTML
    info('Minifying HTML...');
    html = minify(html, {
      collapseWhitespace: false,
      removeComments: false,
      preserveLineBreaks: true,
      minifyCSS: true,
      keepClosingSlash: true
    });

    // Create brand-specific dist directory if it doesn't exist
    const brandName = campaignData.brand.toUpperCase();
    const brandDistDir = path.join(DIST_DIR, brandName, '2026');
    if (!fs.existsSync(brandDistDir)) {
      fs.mkdirSync(brandDistDir, { recursive: true });
    }

    // Write output file to brand/year folder
    const outputPath = path.join(brandDistDir, `${campaignName}.html`);
    fs.writeFileSync(outputPath, html, 'utf8');

    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    success(`Email generated successfully!`);
    success(`Output: ${outputPath}`);
    info(`Build time: ${duration}s`);
    info(`File size: ${(fs.statSync(outputPath).size / 1024).toFixed(2)} KB`);

    // Print stats
    const stats = {
      heroImages: campaignData.heroImages.length,
      bodyParagraphs: campaignData.bodyParagraphs ? campaignData.bodyParagraphs.length : 0,
      ctaEnabled: campaignData.ctaSection?.enabled || false,
      closingModule: campaignData.closingModule?.enabled || false
    };

    console.log('\n' + colors.bright + 'Campaign Stats:' + colors.reset);
    console.log(`  Hero Images: ${stats.heroImages}`);
    console.log(`  Body Paragraphs: ${stats.bodyParagraphs}`);
    console.log(`  CTA Section: ${stats.ctaEnabled ? 'Yes' : 'No'}`);
    console.log(`  Closing Module: ${stats.closingModule ? 'Yes' : 'No'}`);

  } catch (err) {
    error(`Build failed: ${err.message}`);
    console.error(err);
    process.exit(1);
  }
}

// Main execution
if (require.main === module) {
  const args = process.argv.slice(2);

  if (args.length === 0) {
    error('No campaign file specified');
    console.log('\nUsage: node build-email.js <campaign-file.json>');
    console.log('\nExample: node build-email.js valentines-sale.json');
    console.log('\nAvailable campaigns:');

    if (fs.existsSync(CAMPAIGNS_DIR)) {
      const campaigns = fs.readdirSync(CAMPAIGNS_DIR).filter(f => f.endsWith('.json'));
      if (campaigns.length > 0) {
        campaigns.forEach(c => console.log(`  • ${c}`));
      } else {
        console.log('  No campaigns found in /campaigns directory');
      }
    }

    process.exit(1);
  }

  const campaignFile = args[0];
  buildEmail(campaignFile);
}

module.exports = { buildEmail };
