/**
 * Switch between light and dark themes (color modes)
 */

;(() => {
  'use strict'

  const getStoredTheme = () => {
    const theme = localStorage.getItem('theme')
    return ['light', 'dark', 'auto'].includes(theme) ? theme : null
  }
  const setStoredTheme = (theme) => localStorage.setItem('theme', theme)

  const getPreferredTheme = () => {
    const storedTheme = getStoredTheme()
    if (storedTheme) {
      return storedTheme
    }

    // Set default theme to 'light'.
    // Possible options: 'dark' or system color mode (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
    return 'light'
  }

  const setTheme = (theme) => {
    if (theme === 'auto') {
      document.documentElement.setAttribute('data-bs-theme', (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'))
    } else {
      document.documentElement.setAttribute('data-bs-theme', theme)
    }
  }

  setTheme(getPreferredTheme())

  try {
    document.documentElement.dataset.fbSidebarCollapsed =
      localStorage.getItem('fireball.admin.sidebar.collapsed') === '1' ? 'true' : 'false'
  } catch (error) {
    document.documentElement.dataset.fbSidebarCollapsed = 'false'
  }

  const showActiveTheme = (theme, focus = false) => {
    const themeSwitcher = document.querySelector('.theme-switcher')

    if (!themeSwitcher) {
      return
    }

    const activeThemeIcon = document.querySelector('.theme-icon-active i')
    const btnToActive = document.querySelector(
      `[data-bs-theme-value="${theme}"]`
    )
    if (!btnToActive || !activeThemeIcon) {
      return
    }
    const iconOfActiveBtn = btnToActive.querySelector('.theme-icon i').className

    document.querySelectorAll('[data-bs-theme-value]').forEach((element) => {
      element.classList.remove('active')
      element.setAttribute('aria-pressed', 'false')
    })

    btnToActive.classList.add('active')
    btnToActive.setAttribute('aria-pressed', 'true')
    activeThemeIcon.className = iconOfActiveBtn
    const themeLabel = themeSwitcher.dataset.themeLabel || 'Theme'
    themeSwitcher.setAttribute('aria-label', `${themeLabel}: ${btnToActive.textContent.trim()}`)

    if (focus) {
      themeSwitcher.focus()
    }
  }

  window
    .matchMedia('(prefers-color-scheme: dark)')
    .addEventListener('change', () => {
      const storedTheme = getStoredTheme()
      if (storedTheme !== 'light' && storedTheme !== 'dark') {
        setTheme(getPreferredTheme())
      }
    })

  window.addEventListener('DOMContentLoaded', () => {
    showActiveTheme(getPreferredTheme())

    document.querySelectorAll('[data-bs-theme-value]').forEach((toggle) => {
      toggle.addEventListener('click', () => {
        const theme = toggle.getAttribute('data-bs-theme-value')
        setStoredTheme(theme)
        setTheme(theme)
        showActiveTheme(theme, true)
      })
    })
  })
})()
