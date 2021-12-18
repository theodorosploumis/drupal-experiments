describe('Visit homepage', () => {
  it('Sees login form', () => {
    cy.visit('/')
    cy.contains('Log in').should('be.visible')
  })
})
