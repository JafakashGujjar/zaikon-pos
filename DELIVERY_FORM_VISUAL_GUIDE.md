# Delivery Form Visual Design Guide

## Color Palette

### Primary Colors
- **Yellow Gradient**: `linear-gradient(135deg, #FFD700 0%, #f0c419 100%)`
- **Green Badge**: `linear-gradient(135deg, #10b981 0%, #059669 100%)`
- **White**: `#ffffff`
- **Light Gray Background**: `#fafafa`

### Border & Shadow Colors
- **Border Light**: `#e5e7eb`
- **Border Medium**: `#d1d5db`
- **Border Dark**: `#cbd5e0`
- **Text Dark**: `#1a1a1a`
- **Text Medium**: `#374151`
- **Text Light**: `#6b7280`
- **Placeholder**: `#9ca3af`

## Typography

### Section Titles
```css
Font Size: 15px
Font Weight: 700 (Bold)
Letter Spacing: 0.8px
Text Transform: UPPERCASE
Color: #1a1a1a
```

### Labels
```css
Font Size: 13px
Font Weight: 600 (Semi-Bold)
Letter Spacing: 0.2px
Color: #1a1a1a
```

### Input Text
```css
Font Size: 15px (16px on mobile)
Font Weight: 500 (Medium)
Color: #1a1a1a
```

### Button Text
```css
Font Size: 16px
Font Weight: 700 (Bold)
Letter Spacing: 0.3px (primary), 0.2px (secondary)
```

## Spacing System

### Modal
- Padding: 24px-28px (desktop), 18px-20px (mobile)
- Border Radius: 20px
- Gap between sections: 20px

### Cards (Sections)
- Padding: 20px (desktop), 16px (mobile)
- Border Radius: 12px
- Margin Bottom: 20px (desktop), 16px (mobile)

### Form Fields
- Gap between fields: 18px (desktop), 16px (mobile)
- Label margin bottom: 10px
- Input padding: 15px 18px
- Input with icon: 50px left padding

### Buttons
- Padding: 16px 32px (primary), 16px 28px (secondary)
- Gap between buttons: 14px
- Height: 52px minimum

## Component Specifications

### Modal Header
```
Background: linear-gradient(135deg, #FFD700 0%, #f0c419 100%)
Padding: 24px 28px
Box Shadow: 0 2px 8px rgba(0, 0, 0, 0.1)

Icon:
- Size: 26px
- Background: rgba(0, 0, 0, 0.1)
- Padding: 6px
- Border Radius: 10px

Close Button:
- Size: 40x40px
- Background: rgba(0, 0, 0, 0.15)
- Hover: rgba(0, 0, 0, 0.25) + rotate(90deg)
```

### Section Cards
```
Background: #ffffff
Border: 2px solid #e5e7eb
Border Radius: 12px
Padding: 20px
Box Shadow: 0 2px 4px rgba(0, 0, 0, 0.05)
Margin Bottom: 20px

Hover State:
- Border Color: var(--zaikon-yellow)
- Box Shadow: 0 4px 12px rgba(255, 215, 0, 0.15)

Order Summary Special:
- Background: linear-gradient(135deg, #fff9e6 0%, #fff 100%)
- Border Color: var(--zaikon-yellow)
```

### Section Title
```
Icon Wrapper:
- Background: var(--zaikon-yellow)
- Padding: 8px
- Border Radius: 8px
- Size: 20px
- Color: #000
```

### Input Fields
```
Border: 2px solid #e5e7eb
Border Radius: 12px
Padding: 15px 18px (50px left when with icon)
Min Height: 52px (54px on mobile)
Font Size: 15px (16px on mobile)

Focus State:
- Border Color: var(--zaikon-yellow)
- Box Shadow: 0 0 0 4px rgba(255, 215, 0, 0.25)
- Transform: translateY(-1px)

Hover State (not focused):
- Border Color: #cbd5e0

Readonly State:
- Background: #f9fafb
- Color: #6b7280
- Cursor: not-allowed
```

### Field Icons
```
Size: 20px
Position: 16px from left
Color: #6b7280

On Focus:
- Color: var(--zaikon-yellow)
- Transform: scale(1.1)

Animation: 0.3s ease for color, 0.2s ease for transform
```

### Order Summary Cards
```
Grid: 2 columns (1 on mobile)
Gap: 16px (12px on mobile)

Card:
- Background: #fff
- Border: 2px solid #e5e7eb
- Border Radius: 10px
- Padding: 14px

Icon Wrapper:
- Size: 44x44px
- Background: linear-gradient(135deg, #FFD700 0%, #f0c419 100%)
- Border Radius: 10px
- Icon Size: 22px
- Icon Color: #000

Label:
- Font Size: 12px
- Font Weight: 600
- Text Transform: UPPERCASE
- Letter Spacing: 0.5px
- Color: #6b7280

Value:
- Font Size: 16px
- Font Weight: 700
- Color: #1a1a1a
```

### Free Delivery Badge
```
Background: linear-gradient(135deg, #10b981 0%, #059669 100%)
Padding: 8px 16px
Border Radius: 24px
Font Size: 11px
Font Weight: 700
Letter Spacing: 0.8px
Text Transform: UPPERCASE
Color: #fff
Box Shadow: 0 2px 8px rgba(16, 185, 129, 0.3)

Animation: badge-pulse 2s ease-in-out infinite
@keyframes badge-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
```

### Primary Button
```
Background: linear-gradient(135deg, #FFD700 0%, #f0c419 100%)
Border: 2px solid var(--zaikon-yellow)
Padding: 16px 32px
Font Size: 16px
Font Weight: 700
Border Radius: 12px
Min Height: 52px
Box Shadow: 0 4px 12px rgba(255, 215, 0, 0.3)
Letter Spacing: 0.3px

Hover State:
- Background: linear-gradient(135deg, #f0c419 0%, #FFD700 100%)
- Transform: translateY(-3px)
- Box Shadow: 0 8px 20px rgba(255, 215, 0, 0.4)

Active State:
- Transform: translateY(-1px)
- Box Shadow: 0 4px 12px rgba(255, 215, 0, 0.3)
```

### Secondary Button
```
Background: #fff
Border: 2px solid #d1d5db
Color: #374151
Padding: 16px 28px
Font Size: 16px
Font Weight: 600
Border Radius: 12px
Min Height: 52px
Letter Spacing: 0.2px

Hover State:
- Background: #f3f4f6
- Border Color: #9ca3af
- Color: #1f2937
- Transform: translateY(-2px)
- Box Shadow: 0 4px 12px rgba(0, 0, 0, 0.1)

Active State:
- Transform: translateY(0)
- Box Shadow: none
```

### Textarea
```
Min Height: 72px (80px on mobile)
Line Height: 1.6
Resize: vertical
```

## Animations

### Pulse (Required Field Indicator)
```css
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
Duration: 2s ease-in-out infinite
```

### Badge Pulse
```css
@keyframes badge-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
Duration: 2s ease-in-out infinite
```

### Close Button Rotation
```css
Hover: transform: rotate(90deg)
Transition: all 0.2s ease
```

### Icon Scale on Focus
```css
Transform: scale(1.1)
Transition: transform 0.2s ease
```

### Input Lift on Focus
```css
Transform: translateY(-1px)
Transition: all 0.3s ease
```

## Responsive Breakpoints

### Tablet (< 768px)
- Modal takes full screen
- Border radius removed
- Sections: 16px padding
- Grid: All fields stack vertically
- Order summary: Single column
- Buttons: Full width, primary button first

### Mobile (< 480px)
- Header title: 18px
- All touch targets: 54px minimum
- Enhanced spacing for thumbs

## Icon Mapping

### Form Icons (WordPress Dashicons)
- **Header**: `dashicons-location-alt`
- **Customer Section**: `dashicons-businessperson`
- **Customer Name**: `dashicons-admin-users`
- **Phone**: `dashicons-phone`
- **Address Section**: `dashicons-admin-home`
- **Area**: `dashicons-location`
- **Address Field**: `dashicons-building`
- **Instructions**: `dashicons-testimonial`
- **Order Summary**: `dashicons-cart`
- **Distance**: `dashicons-admin-site-alt3`
- **Charge**: `dashicons-money-alt`
- **Rider Section**: `dashicons-groups`
- **Rider Select**: `dashicons-admin-users`
- **Cancel Button**: `dashicons-no-alt`
- **Confirm Button**: `dashicons-yes-alt`

## Accessibility Features

1. **High Contrast**: All text meets WCAG AA standards
2. **Touch Targets**: Minimum 52px (54px on mobile)
3. **Focus Indicators**: Clear visual feedback
4. **Required Fields**: Visual indicator with pulsing animation
5. **Error States**: Clear error messages and styling
6. **Keyboard Navigation**: Full support maintained
7. **Screen Reader**: Semantic HTML structure

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- CSS Grid for layout
- Flexbox for component alignment
- CSS animations and transforms
- Linear gradients
- Box shadows
- No JavaScript required for visual effects

## Performance Considerations

- Hardware-accelerated transforms (translateY, scale)
- Simple animations for smooth performance
- No complex calculations
- Efficient CSS selectors
- Minimal repaints and reflows
