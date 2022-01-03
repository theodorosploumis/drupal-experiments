describe('Contact page', () => {
  it('Browse to Contact page', () => {
    cy.visit('/en/contact')
    cy.contains('Website feedback').should('be.visible')
  })
})
