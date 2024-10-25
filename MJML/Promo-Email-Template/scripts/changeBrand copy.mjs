import fs from 'fs';
import readline from 'readline';
import content from '../content.json' assert { type: 'json' };;


// Path to the code file 
const header = '../components/header.mjml';
const hero = '../components/hero.mjml';
const bodyCopy = '../components/body-copy.mjml';
const orderedList = '../components/ordered-list.mjml';
const footer = '../components/footer.mjml';
const input = '../input.mjml';

const bulletPoints = {
  "need_list": content.list.need_list,
  1: content.list.bullet_1,
  2: content.list.bullet_2,
  3: content.list.bullet_3,
  4: content.list.bullet_4,
  5: content.list.bullet_5
}



// Setup readline interface for terminal commands
const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

// Function to modify the styles and content
function changeContent(brand) {

  // Read the Style sheets
  fs.readFile(input, 'utf8', (err, data) => {
    if (err) {
      console.error(`Error reading file: ${err}`);
      return;
    }

    //Changes style files depending on the brand input
    let updatedData = data
      .replace(/(<mj-include path="\.\/styles\/template-styles-)(p66|76|con|p97)(\.mjml" \/>)/g, `<mj-include path="\.\/styles\/template-styles-${brand}.mjml" \/>`)
      .replace(/(<mj-include path="\.\/styles\/inline-styles-)(p66|76|con|p97)(\.mjml" \/>)/g, `<mj-include path="\.\/styles\/inline-styles-${brand}.mjml" \/>`)
      .replace(/<mj-title>.*?<\/mj-title>/, `<mj-title>${content.title}</mj-title>`);

    //Rewrite input file
    fs.writeFile(input, updatedData, 'utf8', err => {
      if (err) {
        console.log(style)
        console.error(err);
      } else {
        console.log('Input file successfully updated.');
      }
    })
   

  });
  
  // Read the Footer
  fs.readFile(footer, 'utf8', (err, data) => {
    if (err) {
      console.error(`Error reading file: ${err}`);
      return;
    }

    //Changes utm codes 
    let updatedData = data
      .replaceAll(/(utm_campaign=)[^"]+/g, `utm_campaign=${content.utm}`)
      .replace(/(<mj-text[^>]*class=["'][^"']*legal[^"']*["'][^>]*>)([\s\S]*?)(<\/mj-text>)/, `$1${content.legal}$3`);

    //Rewrite footer
    fs.writeFile(footer, updatedData, 'utf8', err => {
      if (err) {
        console.error(err);
      } else {
        console.log('Footer file successfully updated.');
      }
    })
   

   });

  // // Read the Header
  fs.readFile(header, 'utf8', (err, data) => {
    if (err) {
      console.error(`Error reading file: ${err}`);
      return;
    }

    //Changes utm codes 
    let updatedData = data
    .replaceAll(/(utm_campaign=)[^"]+/g, `utm_campaign=${content.utm}`);

    //Rewrite header
    fs.writeFile(header, updatedData, 'utf8', err => {
      if (err) {
        console.error(err);
      } else {
        console.log('Header file successfully updated.');
      }
    })
   

   });

  // Read the Body
  fs.readFile(bodyCopy, 'utf8', (err, data) => {
    if (err) {
      console.error(`Error reading file: ${err}`);
      return;
    }

    //Changes body content 
    let updatedData = data
      .replace(/(<mj-image [^>]*src=")[^"]+(")/g, `$1${content.hero}$2`)
      .replace(/(<mj-text[^>]*class=["'][^"']*head1[^"']*["'][^>]*>)([\s\S]*?)(<\/mj-text>)/, `$1${content.title}$3`)
      .replace(/(<mj-text[^>]*class=["'][^"']*head4[^"']*["'][^>]*>)([\s\S]*?)(<\/mj-text>)/, `$1${content.copy}$3`)
      .replace(/(<mj-button[^>]*>)([\s\S]*?)(<\/mj-button>)/, `$1${content.btn_copy}$3`)
      .replace(/(<mj-text[^>]*class="[^"]*ordered-list-(\d+)[^"]*"[^>]*>)[\s\S]*?(<\/mj-text>)/g, (match, p1, p2, p3) => {
        // Access the bullet point by key number
        const bullet = bulletPoints[p2] || ''; // Fallback to empty if key is not found
        return `${p1}${bullet}${p3}`;
      })
      // Checks to see if the list needs to be added, if it returns false, turns the display off
      .replaceAll(/(<mj-section[^>]*class="[^"]*display-([^"\s]*)[^"]*"[^>]*>)/gi, (match, p1, p2) => {
        // Determine new class based on checkList() result
        const newDisplayClass = checkList() === true ? 'display-show' : 'display-none';
        // Return the section with the updated class
        return match.replace(`display-${p2}`, newDisplayClass);
      });
    

    //Rewrite bodyCopy
    fs.writeFile(bodyCopy, updatedData, 'utf8', err => {
      if (err) {
        console.error(err);
      } else {
        console.log('Body copy file successfully updated.');
      }
    })
  });

}


function checkList() {
  if (content.list.need_list == 'true') {
    return true;
  } else {
    return false;
  }
}



// Prompt the user for the brand
rl.question('What brand would you like to create? Your options are p66, con, 76 or P97.', (brand) => {
  const lowerBrand = brand.toLowerCase();



  console.log(typeof lowerBrand);
  console.log(typeof brand);


  if (lowerBrand != 'p66' && lowerBrand != 'con' && lowerBrand != '76' && lowerBrand != 'p97') {
    console.log('Please type in p66, con, 76 or p97.')
    process.exit(0);
  } else {
    changeContent(lowerBrand);
    rl.close();
  }
  
});