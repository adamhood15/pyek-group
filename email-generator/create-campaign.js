#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const readline = require('readline');

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

function question(prompt) {
  return new Promise((resolve) => {
    rl.question(prompt, resolve);
  });
}

async function createCampaign() {
  console.log('\n📧 Campaign Generator\n');

  const name = await question('Campaign name (e.g., summer-sale): ');
  const title = await question('Email title: ');
  const preheader = await question('Preheader text: ');

  const utmCampaign = await question('UTM campaign name (or press Enter to skip): ');
  const enableUTM = utmCampaign.trim() !== '';

  const heroCount = await question('Number of hero images (1-3): ');
  const heroImages = [];

  for (let i = 0; i < parseInt(heroCount); i++) {
    console.log(`\nHero Image ${i + 1}:`);
    const src = await question('  Image URL: ');
    const alt = await question('  Alt text: ');
    const link = await question('  Link URL: ');
    heroImages.push({ src, alt, link });
  }

  const bodyCount = await question('\nNumber of body paragraphs (0-5): ');
  const bodyParagraphs = [];

  for (let i = 0; i < parseInt(bodyCount); i++) {
    console.log(`\nParagraph ${i + 1}:`);
    const content = await question('  Content (can include HTML): ');
    bodyParagraphs.push({
      content,
      fontSize: '24px',
      lineHeight: '34px',
      padding: '25px 50px 30px 50px'
    });
  }

  const showCTA = await question('\nShow CTA section? (y/n): ');
  let ctaSection = { enabled: false };

  if (showCTA.toLowerCase() === 'y') {
    const showBay = await question('Show Bay button? (y/n): ');
    const showCanyon = await question('Show Canyon button? (y/n): ');

    ctaSection = {
      enabled: true,
      showBay: showBay.toLowerCase() === 'y',
      showCanyon: showCanyon.toLowerCase() === 'y',
      padding: '25px 0 0 0',
      bayUrl: 'https://cowabungavegas.com/',
      bayText: 'GRAB TICKETS',
      canyonUrl: 'https://cowabungavegas.com/',
      canyonText: 'GRAB TICKETS'
    };

    if (ctaSection.showBay) {
      ctaSection.bayUrl = await question('Bay CTA URL: ');
    }
    if (ctaSection.showCanyon) {
      ctaSection.canyonUrl = await question('Canyon CTA URL: ');
    }
  }

  const showClosing = await question('\nShow closing module? (y/n): ');
  let closingModule = { enabled: false };

  if (showClosing.toLowerCase() === 'y') {
    const isImage = await question('Use image (y) or text (n)? ');

    if (isImage.toLowerCase() === 'y') {
      closingModule = {
        enabled: true,
        isImage: true,
        imageSrc: await question('Image URL: '),
        imageAlt: await question('Alt text: '),
        link: await question('Link URL: '),
        padding: '0 0 50px 0'
      };
    } else {
      closingModule = {
        enabled: true,
        isImage: false,
        textContent: await question('Text content (can include HTML): '),
        fontSize: '20px',
        lineHeight: '30px',
        fontWeight: '400',
        padding: '40px 50px 50px 50px'
      };
    }
  }

  const campaign = {
    meta: {
      title,
      preheader
    },
    theme: {
      backgroundColor: '#0285c5',
      textColor: '#ffffff'
    },
    utm: {
      enabled: enableUTM,
      params: enableUTM
        ? `?utm_source=Email&utm_medium=Mailchimp&utm_campaign=${utmCampaign}`
        : ''
    },
    heroImages,
    bodyParagraphs,
    ctaSection,
    closingModule
  };

  const filename = `${name}.json`;
  const filepath = path.join(__dirname, 'campaigns', filename);

  fs.writeFileSync(filepath, JSON.stringify(campaign, null, 2));

  console.log(`\n✅ Campaign created: campaigns/${filename}`);
  console.log(`\nTo build: node build-email.js ${filename}\n`);

  rl.close();
}

createCampaign().catch(err => {
  console.error('Error:', err);
  rl.close();
  process.exit(1);
});
