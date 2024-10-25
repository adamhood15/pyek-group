import fs from 'fs';
import readline from 'readline';
import content from '../content.json' assert { type: 'json' };

const header = '../components/header.mjml';
const hero = '../components/hero.mjml';
const bodyCopy = '../components/body-copy.mjml';
const orderedList = '../components/ordered-list.mjml';
const twoCol = '../components/two-col.mjml';
const cta = '../components/cta.mjml';
const footer = '../components/footer.mjml';
const input = '../input.mjml';

const bulletPoints = {
  "required": content.add_components.list.required,
  1: content.add_components.list.bullet_1,
  2: content.add_components.list.bullet_2,
  3: content.add_components.list.bullet_3,
  4: content.add_components.list.bullet_4,
  5: content.add_components.list.bullet_5
};

const needTwoCol = content.add_components.two_col.required;
const needList = content.add_components.list.required;

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

function changeContent(brand) {
  const files = [footer, header, hero, bodyCopy, cta, orderedList, input];

  const updateFileContent = (filePath, patternReplacements) => {
    fs.readFile(filePath, 'utf8', (err, data) => {
        if (err) {
            console.error(`Error reading file ${filePath}: ${err}`);
            return;
        }

        let updatedData = data;

        // Resetting the brands that were commented out
        updatedData = updatedData.replace(/<!--\s*(<mj-image\s+css-class=["']header-image-[^"']*["'][\s\S]*?)\s*-->/gi, '$1');
        updatedData = updatedData.replace(/<!--\s*(<mj-column\s+css-class=["']no-stack-outlook ca-link["'][\s\S]*?)\s*-->/gi, '$1');
        updatedData = updatedData.replace(/<!--\s*(<mj-image\s+css-class=["'](insta-p66|insta-76|insta-con|fb-p66|fb-76|fb-con)[^>]*>[\s\S]*?)\s*-->/gi, '$1');

        // Iterating over the pattern replacements
        for (const [pattern, replacement] of patternReplacements) {
            const matchFound = updatedData.match(pattern); // Use updatedData for replacements
            if (matchFound) {
                updatedData = updatedData.replace(pattern, replacement);
                console.log(`Pattern found and replaced in ${filePath}:`, matchFound[0]);
            } else {
                console.log(`Pattern not found in ${filePath}, no changes made for: ${pattern}`);
            }
        }

        if (updatedData !== data) {
            fs.writeFile(filePath, updatedData, 'utf8', err => {
                if (err) {
                    console.error(`Error writing file ${filePath}: ${err}`);
                } else {
                    console.log(`${filePath} successfully updated.`);
                }
            });
        } else {
            console.log(`${filePath} has no changes.`);
        }
    });
};

  // Updating each section with relevant content and patterns
  updateFileContent(footer, [
    [/(utm_campaign=)[^"]+/g, `utm_campaign=${content.utm}`],
    [/(<mj-text[^>]*class=["'][^"']*legal[^"']*["'][^>]*>)([\s\S]*?)(<\/mj-text>)/, `$1${content.legal}$3`],
    //Comment out CA link based on brand
    [
      /(<mj-column[^>]*css-class=["']no-stack-outlook ca-link["'][^>]*>[\s\S]*?<\/mj-column>)/,
      brand === 'p66' || brand === 'con' ? `<!-- $1 -->` : '$1'
    ],
    // Comment out social media images based on brand
     // Comment out p66 images if 76 is selected
    [
      /<mj-image[^>]*class=["'][^"']*\b(fb-p66|insta-p66)\b[^"']*["'][^>]*>[\s\S]*?<\/mj-image>/g,
      (brand === '76') ? `<!-- $0 -->` : '$0'
    ],

    // Comment out 76 images if p66 or con is selected
    [
      /<mj-image[^>]*class=["'][^"']*?\b(fb-76|insta-76)\b[^"']*["'][^>]*>[\s\S]*?<\/mj-image>/g,
      (brand === 'p66' || brand === 'con') ? `<!-- $0 -->` : '$0'
    ],

    // Comment out con images if 76 is selected
    [
      /<mj-image[^>]*class=["'][^"']*?\b(fb-con|insta-con)\b[^"']*["'][^>]*>[\s\S]*?<\/mj-image>/g,
      (brand === '76') ? `<!-- $0 -->` : '$0'
    ]
  ]);

  updateFileContent(header, [
    [/(utm_campaign=)[^"]+/g, `utm_campaign=${content.utm}`],
    // Replaces p66/76/con header with commented out code depending on user input
    [/(<mj-image\s+css-class=["']header-image-p66["'][\s\S]*?\/>)/gi, brand === 'p66' ? '$1' : '<!-- $1 -->'],
    [/(<mj-image\s+css-class=["']header-image-76["'][\s\S]*?\/>)/gi, brand === '76' ? '$1' : '<!-- $1 -->'],
    [/(<mj-image\s+css-class=["']header-image-con["'][\s\S]*?\/>)/gi, brand === 'con' ? '$1' : '<!-- $1 -->'],
    [/(<mj-image\s+css-class=["']header-image-p97["'][\s\S]*?\/>)/gi, brand === 'p97' ? '$1' : '<!-- $1 -->']
  ]);

  updateFileContent(hero, [
    [/(<mj-image [^>]*src=")[^"]+(")/g, `$1${content.hero}$2`]
  ]);

  updateFileContent(bodyCopy, [
    [/(<mj-text[^>]*class=["'][^"']*head1[^"']*["'][^>]*>)([\s\S]*?)(<\/mj-text>)/, `$1${content.title}$3`],
    [/(<mj-text[^>]*class=["'][^"']*head4[^"']*["'][^>]*>)([\s\S]*?)(<\/mj-text>)/, `$1${content.copy}$3`]
  ]);

  updateFileContent(cta, [
    [/(<mj-button[^>]*>)([\s\S]*?)(<\/mj-button>)/, `$1${content.btn_copy}$3`],
    [/href="[^"]*"/, `href="${content.btn_url}"`]
  ]);

  updateFileContent(orderedList, [
    [/(<mj-text[^>]*class="[^"]*ordered-list-(\d+)[^"]*"[^>]*>)[\s\S]*?(<\/mj-text>)/g, (match, p1, p2, p3) => {
      const bullet = bulletPoints[p2] || ''; // Fallback to empty if key not found
      return `${p1}${bullet}${p3}`;
    }]
  ]);

  updateFileContent(twoCol, [
    [/(<mj-text[^>]*class=["'][^"']*mobile-col-title-p[^"']*["'][^>]*>)([\s\S]*?)(<\/mj-text>)/, `$1${content.add_components.two_col.title}$3`],
    [/(<mj-text[^>]*class=["'][^"']*mobile-col-copy-p[^"']*["'][^>]*>)([\s\S]*?)(<\/mj-text>)/, `$1${content.add_components.two_col.copy}$3`],
    [/(<mj-image [^>]*src=")[^"]+(")/g, `$1${content.add_components.two_col.hero}$2`]

  ]);


  updateFileContent(input, [
    [/(<mj-include path="\.\/styles\/template-styles-)(p66|76|con|p97)(\.mjml" \/>)/g, `<mj-include path="\.\/styles\/template-styles-${brand}.mjml" \/>`],
    [/(<mj-include path="\.\/styles\/inline-styles-)(p66|76|con|p97)(\.mjml" \/>)/g, `<mj-include path="\.\/styles\/inline-styles-${brand}.mjml" \/>`],
    [/<mj-title>.*?<\/mj-title>/, `<mj-title>${content.browser_title}</mj-title>`]
  ]);

}

includeAdditionalComponents();


function includeAdditionalComponents() {
  fs.readFile(input, 'utf8', (err, data) => {
    if (err) {
      console.error(`Error reading ${input}: ${err}`);
      return;
    }

    let updatedData = data;

    // Handle needList condition
    if (needList === 'true') {
      // Remove comment around ordered-list include if it exists
      updatedData = updatedData.replace(
        /<!--\s*<mj-include path="\.\/components\/ordered-list\.mjml" \/> -->/g,
        '<mj-include path="./components/ordered-list.mjml" />'
      );
    } else if (needList === 'false') {
      // Add comment around ordered-list include only if it’s not already commented
      if (!updatedData.includes('<!-- <mj-include path="./components/ordered-list.mjml" /> -->')) {
        updatedData = updatedData.replace(
          /<mj-include path="\.\/components\/ordered-list\.mjml" \/>/g,
          '<!-- <mj-include path="./components/ordered-list.mjml" /> -->'
        );
      }
    }

    // Handle needTwoCol condition
    if (needTwoCol === 'true') {
      // Remove comment around two-col include if it exists
      updatedData = updatedData.replace(
        /<!--\s*<mj-include path="\.\/components\/two-col\.mjml" \/> -->/g,
        '<mj-include path="./components/two-col.mjml" />'
      );
    } else if (needTwoCol === 'false') {
      // Add comment around two-col include only if it’s not already commented
      if (!updatedData.includes('<!-- <mj-include path="./components/two-col.mjml" /> -->')) {
        updatedData = updatedData.replace(
          /<mj-include path="\.\/components\/two-col\.mjml" \/>/g,
          '<!-- <mj-include path="./components/two-col.mjml" /> -->'
        );
      }
    }

    // Write the updated data back to the file
    fs.writeFile(input, updatedData, 'utf8', (err) => {
      if (err) {
        console.error(`Error writing ${input}: ${err}`);
      } else {
        console.log(`File ${input} updated successfully.`);
      }
    });
  });
}
includeAdditionalComponents();

rl.question('What brand would you like to create? Your options are p66, con, 76 or P97.', (brand) => {
  const lowerBrand = brand.toLowerCase();

  if (!['p66', 'con', '76', 'p97'].includes(lowerBrand)) {
    console.log('Please type in p66, con, 76 or p97.');
    process.exit(0);
  } else {
    changeContent(lowerBrand);
    rl.close();
  }
});