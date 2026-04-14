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
      cy.get('.login-page__logo').should('contain', 'TR3-ENTRADES')
      cy.get('.login-page__title').should('contain', 'Benvingut de nou')
      cy.get('input[type="email"]').should('be.visible')
      cy.get('input[type="password"]').should('be.visible')
      cy.get('button[type="submit"]').should('contain', 'Iniciar sessió')
    })

    it('shows error for invalid credentials', () => {
      cy.get('input[type="email"]').type('invalid@example.com')
      cy.get('input[type="password"]').type('wrongpassword')
      cy.get('button[type="submit"]').click()
      cy.get('[role="alert"]').should('contain', 'Error')
    })

    it('can navigate to register page', () => {
      cy.get('footer a.login-page__link-cta').click()
      cy.url().should('include', '/register')
      cy.get('.login-page__title').should('contain', 'Crea el teu compte')
    })
  })

  describe('Register form', () => {
    beforeEach(() => {
      cy.visit('/register')
    })

    it('shows register form fields', () => {
      cy.get('#register-name').should('be.visible')
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