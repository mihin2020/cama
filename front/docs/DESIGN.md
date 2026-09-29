---
name: CAMA Design System
colors:
  surface: '#fbf9f8'
  surface-dim: '#dcd9d9'
  surface-bright: '#fbf9f8'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f6f3f2'
  surface-container: '#f0eded'
  surface-container-high: '#eae7e7'
  surface-container-highest: '#e4e2e1'
  on-surface: '#1b1c1c'
  on-surface-variant: '#5c403f'
  inverse-surface: '#303030'
  inverse-on-surface: '#f3f0f0'
  outline: '#906f6e'
  outline-variant: '#e5bdbb'
  surface-tint: '#bf0229'
  primary: '#9e001f'
  on-primary: '#ffffff'
  primary-container: '#c8102e'
  on-primary-container: '#ffdad8'
  inverse-primary: '#ffb3b1'
  secondary: '#006e27'
  on-secondary: '#ffffff'
  secondary-container: '#7ff98d'
  on-secondary-container: '#007329'
  tertiary: '#745b00'
  on-tertiary: '#ffffff'
  tertiary-container: '#d0a600'
  on-tertiary-container: '#4f3d00'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffdad8'
  primary-fixed-dim: '#ffb3b1'
  on-primary-fixed: '#410007'
  on-primary-fixed-variant: '#92001c'
  secondary-fixed: '#81fc90'
  secondary-fixed-dim: '#65df76'
  on-secondary-fixed: '#002107'
  on-secondary-fixed-variant: '#00531c'
  tertiary-fixed: '#ffe08a'
  tertiary-fixed-dim: '#f1c100'
  on-tertiary-fixed: '#241a00'
  on-tertiary-fixed-variant: '#574400'
  background: '#fbf9f8'
  on-background: '#1b1c1c'
  surface-variant: '#e4e2e1'
typography:
  display-lg:
    fontFamily: Montserrat
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Montserrat
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-lg-mobile:
    fontFamily: Montserrat
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  headline-md:
    fontFamily: Montserrat
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  title-lg:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.05em
  caption:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  unit: 4px
  container-max-width: 1200px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 32px
  stack-sm: 8px
  stack-md: 16px
  stack-lg: 32px
---

## Brand & Style
The design system for CAMA (Caisse d'Assurance Maladie des Armées) is built on the pillars of **Duty, Protection, and Reliability**. It serves a dual purpose: honoring the military heritage of Burkina Faso while providing a modern, accessible healthcare administration interface for service members and their families.

The visual style is **Corporate / Modern** with a slight leaning toward **Sovereign Minimalism**. It prioritizes clarity and authority, avoiding unnecessary decorative flourishes in favor of structural integrity. The emotional response should be one of stability and institutional trust—reassuring the user that their health and administrative needs are handled with military precision and national care.

## Colors
This design system utilizes a high-contrast palette derived from the national colors of Burkina Faso, optimized for administrative legibility.

- **CAMA Red (#C8102E):** Used primarily for critical actions, branding, and primary navigation highlights. It represents the strength and vitality of the armed forces.
- **Burkina Green (#009739):** Applied to success states and secondary institutional elements. It provides a grounding, organic balance to the red.
- **Gold (#F2C200):** Reserved for accents, pending statuses, and special "Gold Medallion" tier information. It signifies excellence and value.
- **Anthracite (#2F2F2F):** The primary color for typography and deep structural elements, ensuring high readability and a professional, "Uniform" feel.
- **Off-white (#F8F8F6):** A clean, soft background color that reduces eye strain compared to pure white, maintaining an "official document" aesthetic.

## Typography
The typography strategy pairs the geometric strength of **Montserrat** for headings with the systematic clarity of **Inter** for body text and data.

- **Headings:** Set in Montserrat (Bold/Semi-bold). These should feel impactful and authoritative. In French, headings tend to be longer; ensure line heights are generous to prevent crowding.
- **Body & Data:** Inter is used for all functional text. Its high x-height ensures legibility for medical codes, dates, and administrative tables.
- **Labels:** Uppercase labels with slight letter spacing are used for form headers and category tags to mimic the look of official military identification and dossiers.

## Layout & Spacing
The layout follows a **Fixed Grid** model for desktop to maintain a disciplined, centered focus, and a **Fluid Grid** for mobile devices.

- **Grid:** A 12-column grid is standard for desktop (1200px max width). For mobile, a 4-column grid is used.
- **Rhythm:** An 8px base unit (2 x 4px unit) governs all spacing. Vertical stacks of information should be clearly separated to prevent the "wall of text" effect common in administrative portals.
- **Density:** Elements are spaced with "Professional Breathing Room"—not as airy as a consumer startup, but never cramped. This ensures that users in high-stress situations (medical emergencies) can scan and find information quickly.

## Elevation & Depth
Depth in this design system is used to indicate **Hierarchy of Importance** rather than physical realism.

- **Surface Layers:** Use a "Tonal Layering" approach. The main canvas is Off-white (#F8F8F6). Cards and containers are Pure White (#FFFFFF) to make them pop forward.
- **Shadows:** Use extremely subtle, large-radius shadows (e.g., `box-shadow: 0 4px 20px rgba(47, 47, 47, 0.05)`). The shadow color should be a tint of the Anthracite neutral, never pure black.
- **Borders:** Use low-contrast outlines (1px solid #E0E0E0) for secondary elements like input fields and inactive cards to maintain a clean, grid-aligned structure without relying solely on shadows.

## Shapes
The shape language is **Structured and Disciplined**. We avoid fully rounded "pill" shapes for primary actions to maintain a professional, institutional character.

- **Corner Radius:** A standard 4px to 6px radius is applied to all buttons, cards, and input fields. This "Soft" (Level 1) approach takes the edge off the industrial feel without becoming overly casual or "bouncy."
- **Icons:** Use sharp or slightly rounded 24px stroke icons. Avoid "filled" bubbly icons; instead, use consistent line-weights that match the weight of the Inter typeface.

## Components
Consistent component behavior ensures the CAMA platform feels like a single, unified tool.

- **Buttons:** 
  - **Primary:** Solid Red (#C8102E) with white text. High emphasis for "Soumettre" or "Valider."
  - **Secondary:** Solid Green (#009739) for positive but non-primary actions.
  - **Tertiary:** Outlined Anthracite for "Annuler" or "Retour."
- **Cards:** White background, 4px border radius, 1px light border. Used for "Dossiers Patients" or "Réclamations."
- **Status Indicators (Chips):**
  - **Urgent:** Red background with white text, bold weight.
  - **En attente:** Gold background with Anthracite text.
  - **Terminé:** Green background with white text.
- **Input Fields:** 1px Anthracite border (30% opacity). On focus, the border thickens to 2px and changes to CAMA Red.
- **Protective Silhouette Symbol:** Use as a subtle watermark in the background of headers or as a loading state animation to reinforce the brand's mission of protection.