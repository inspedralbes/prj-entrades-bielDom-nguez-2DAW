describe('Auth UI - Login/Register/Checkout', () => {
  const baseUrl = Cypress.env('apiUrl') || 'http://localhost:8000'

  beforeEach(() => {
    cy.visit('/login')
    cy.clearCookies()
    cy.window().then(win => {
      win.$nuxt.$pinia.state.value.auth = { token: null, user: null }
    })
  })

  describe('Login form', () => {
    it('shows login form by default', () => {
      cy.get('h1').should('contain', 'Inici de sessió')
      cy.get('input[type="email"]').should('be.visible')
      cy.get('input[type="password"]').should('be.visible')
      cy.get('button[type="submit"]').should('contain', 'Iniciar sessió')
    })

    it('shows error for invalid credentials', () => {
      cy.get('input[type="email"]').type('invalid@example.com')
      cy.get('input[type="password"]').type('wrongpassword')
      cy.get('button[type="submit"]').click()
      cy.get('.text-red-600').should('contain', 'Error')
    })

    it('can switch to register mode', () => {
      cy.contains('Registra-t').click()
      cy.get('h1').should('contain', 'Registre')
    })
  })

  describe('Register form', () => {
    it('shows register form fields', () => {
      cy.contains('Registra-t').click()
      cy.get('input[type="text"]').first().should('have.attr', 'placeholder', undefined)
      cy.get('button[type="submit"]').should('contain', 'Crear compte')
    })
  })

  describe('Protected routes', () => {
    it('redirects /tickets to login when not authenticated', () => {
      cy.visit('/tickets')
      cy.url().should('include', '/login')
    })

    it('redirects /checkout to login when not authenticated', () => {
      cy.visit('/checkout')
      cy.url().should('include', '/login')
    })

    it('redirects /social to login when not authenticated', () => {
      cy.visit('/social')
      cy.url().should('include', '/login')
    })

    it('redirects /saved to login when not authenticated', () => {
      cy.visit('/saved')
      cy.url().should('include', '/login')
    })

    it('redirects /profile to login when not authenticated', () => {
      cy.visit('/profile')
      cy.url().should('include', '/login')
    })
  })
})