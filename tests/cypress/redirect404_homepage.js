describe('Redirect 404 page to home', () => {
  it('User Reset password form', () => {
    cy.visit('/en/some404path')
    cy.contains('Super easy vegetarian pasta bake').should('be.visible')
  })
})
