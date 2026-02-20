const fs = require('fs');
const path = require('path');

const templatePath = path.join(__dirname, 'templates', 'promo-template.hbs');
let template = fs.readFileSync(templatePath, 'utf8');

// 1. Replace CTA section with brand-aware version
const oldCtaStart = '{{#if ctaSection.enabled}}\n                                     <tr>\n                                        <td style="padding: {{ctaSection.padding}};background-color:{{theme.backgroundColor}};" class="dark-mode">\n                                            <table width="600" border="0" align="center"\n                                            cellpadding="0" cellspacing="0"\n                                            style="width: 600px"\n                                            role="presentation" bgcolor="{{theme.backgroundColor}}">\n                                            <tr>\n                                                {{#if ctaSection.showBay}}';

const newCtaStart = `{{#if ctaSection.enabled}}
                                    {{#if (eq brandConfig.cta.type "single")}}
                                    <!-- Single CTA for TTA/TTH -->
                                     <tr>
                                        <td style="padding: {{#if ctaSection.padding}}{{ctaSection.padding}}{{else}}25px 0 50px 0{{/if}};background-color:{{theme.backgroundColor}};" class="dark-mode" align="center">
                                            <!--[if mso]>
                                                <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ctaSection.ctaUrl}}{{#if utm.enabled}}{{utm.params}}{{/if}}" style="height:60px;v-text-anchor:middle;width:200px;" arcsize="45%" strokecolor="#ffffff" strokeweight="4px" fillcolor="{{#if ctaSection.ctaColor}}{{ctaSection.ctaColor}}{{else}}{{brandConfig.cta.primaryColor}}{{/if}}">
                                                    <w:anchorlock/>
                                                    <center style="color:#ffffff;font-family:'Arial Black',sans-serif;font-size:18px;font-weight:900;line-height:26px;mso-line-height-rule:exactly;">
                                                    {{ctaSection.ctaText}}
                                                    </center>
                                                </v:roundrect>
                                                <![endif]-->
                                                <!--[if !mso]><!-->
                                            <a class="cta-yellow" href="{{ctaSection.ctaUrl}}{{#if utm.enabled}}{{utm.params}}{{/if}}" style="display:inline-block;background-color:{{#if ctaSection.ctaColor}}{{ctaSection.ctaColor}}{{else}}{{brandConfig.cta.primaryColor}}{{/if}};color:#ffffff;font-family:'Arial Black',sans-serif;font-size:18px;font-weight:900;line-height:26px;text-align:center;text-decoration:none;-webkit-border-radius:35px;-moz-border-radius:35px;border-radius:35px;border:4px solid #ffffff;padding:10px 20px;">
                            {{ctaSection.ctaText}}
                                            </a>
                                            <!--<![endif]-->
                                        </td>
                                    </tr>
                                    {{else}}
                                    <!-- Dual CTA for CBV -->
                                     <tr>
                                        <td style="padding: {{#if ctaSection.padding}}{{ctaSection.padding}}{{else}}25px 0 0 0{{/if}};background-color:{{theme.backgroundColor}};" class="dark-mode">
                                            <table width="600" border="0" align="center"
                                            cellpadding="0" cellspacing="0"
                                            style="width: 600px"
                                            role="presentation" bgcolor="{{theme.backgroundColor}}">
                                            <tr>
                                                {{#if ctaSection.showBay}}`;

template = template.replace(oldCtaStart, newCtaStart);

// 2. Add closing for the brand conditional before {{/if}} at end of CTA section
const oldCtaEnd = '                                            </tr>\n                                            </table>\n                                        </td>\n                                    </tr>\n                                    {{/if}}';

const newCtaEnd = `                                            </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    {{/if}}
                                    {{/if}}`;

template = template.replace(oldCtaEnd, newCtaEnd);

// 3. Replace footer logo with brand-specific logo
template = template.replace(
  '<img src="https://8fad495daf803ee9734d-e7400e7d30316edc82322403d4af119e.ssl.cf5.rackcdn.com/Email/PYEK-0134/cowabunga-logo.png"\n                                                                                              alt="Cowabunga Waterpark ®"\n                                                                                              width="96"',
  '<img src="{{brandConfig.logo.src}}"\n                                                                                              alt="{{brandConfig.logo.alt}}"\n                                                                                              width="{{brandConfig.logo.width}}"'
);

// 4. Replace footer address with brand-specific address
template = template.replace(
  '900 Galleria Drive<br>\n                                                                                                      Henderson, NV 89011<br><br>',
  '{{brandConfig.footer.address}}<br>\n                                                                                                      {{brandConfig.footer.city}}<br><br>'
);

// 5. Replace company name in footer
template = template.replace(
  'Cowabunga\n                                                                                                      Vegas',
  '{{brandConfig.footer.companyName}}'
);

// Write updated template
fs.writeFileSync(templatePath, template);

console.log('✓ Template updated successfully!');
console.log('✓ Added single CTA support for TTA/TTH');
console.log('✓ Added brand-specific footer');
console.log('✓ Added dynamic CTA colors');
