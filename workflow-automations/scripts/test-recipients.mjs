// Test-send stages. Every campaign goes to the internal group (Development team) first (--test).
// Only after that is approved does it go to the approval group (Brand Team) (--test-approved).
export const TEST_RECIPIENTS = {
  internal: [
    "adamhood3700@gmail.com",
    "adamhood3700@yahoo.com",
    "adam.hood@pyek.com"
  ],
  approved: [
    "connor.arndt@pyek.com",
    "connor.pyek@gmail.com",
    "connor.pyek@yahoo.com",
    "natalia.castro@pyek.com"
  ]
};
