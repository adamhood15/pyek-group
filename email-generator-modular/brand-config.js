// Brand configurations for multi-park email system

const brands = {
  cbv: {
    name: 'Cowabunga Bay & Canyon',
    shortName: 'CBV',
    logo: {
      src: 'https://8fad495daf803ee9734d-e7400e7d30316edc82322403d4af119e.ssl.cf5.rackcdn.com/Email/PYEK-0134/cowabunga-logo.png',
      alt: 'Cowabunga Waterpark ®',
      width: '96'
    },
    footer: {
      address: '900 Galleria Drive',
      city: 'Henderson, NV 89011',
      companyName: 'Cowabunga Vegas'
    },
    cta: {
      type: 'dual', // dual or single
      primaryColor: '#ff1469', // Bay pink
      secondaryColor: '#04868b', // Canyon green
      primaryLogo: 'https://cowabunga-vegas.s3.us-east-1.amazonaws.com/bay-and-canyon/2025/PYEK-CBV-25-021-Now-Open-Weekends/bay-logo.png',
      secondaryLogo: 'https://cowabunga-vegas.s3.us-east-1.amazonaws.com/bay-and-canyon/2025/PYEK-CBV-25-021-Now-Open-Weekends/cbc-logo-min.png',
      primaryAlt: 'Cowabunga Bay Logo',
      secondaryAlt: 'Cowabunga Canyon Logo'
    },
    defaultTheme: {
      backgroundColor: '#0285c5',
      textColor: '#ffffff'
    }
  },

  tta: {
    name: 'Typhoon Texas Austin',
    shortName: 'TTA',
    logo: {
      src: 'https://typhoon-texas.s3.us-east-1.amazonaws.com/template/Typhoon-Texas-footer-logo-min.png',
      alt: 'Typhoon Texas Waterpark ®',
      width: '110'
    },
    footer: {
      address: '18500 TX-130 North Service Road',
      city: 'Pflugerville, TX 78660',
      companyName: 'Typhoon Texas Austin'
    },
    cta: {
      type: 'single',
      primaryColor: '#ffe00c', // Yellow
      secondaryColor: '#ff1469' // Pink (alternate)
    },
    defaultTheme: {
      backgroundColor: '#0285c5',
      textColor: '#ffffff'
    }
  },

  tth: {
    name: 'Typhoon Texas Houston',
    shortName: 'TTH',
    logo: {
      src: 'https://664df04dd5a9a5878bdd-e7400e7d30316edc82322403d4af119e.ssl.cf1.rackcdn.com/Email/PYEK-0016%20Payment%20Plan%20Email/Typhoon-Texas-header-logo.png',
      alt: 'Typhoon Texas Waterpark ®',
      width: '110'
    },
    footer: {
      address: '555 Katy Fort Bend Rd',
      city: 'Katy, TX 77494',
      companyName: 'Typhoon Texas Houston'
    },
    cta: {
      type: 'single',
      primaryColor: '#ffe00c', // Yellow
      secondaryColor: '#ff1469' // Pink (alternate)
    },
    defaultTheme: {
      backgroundColor: '#0285c5',
      textColor: '#ffffff'
    }
  }
};

module.exports = brands;
