describe('User reset password page', () => {
  it('User Reset password form', () => {
    const id = Date.now().toString()

    cy.visit('/user/password')
    cy.get('input[name=name]').type(`john_doe_${id}@domain.com`)
    cy.get('#edit-submit').click()
    cy.contains('an email will be sent with instructions to reset your password').should('be.visible')
  })
})
