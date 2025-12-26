This PR enhances the preview image display in the Element Templates admin GridField.

## Changes

### Summary Fields Reordering
- Moved Preview Image to first column for better visibility
- Order now: Preview Image → Title → Page Type

### Click-to-Enlarge Functionality
- Added custom `getLayoutImageThumbnail()` method
- Thumbnails scaled to 200px width maintaining aspect ratio
- Click thumbnail to view 800px enlarged version in dark overlay
- `event.stopPropagation()` prevents GridField row click navigation
- Images render as DBHTMLText for proper display (not escaped)

### User Experience Improvements
- Preview images no longer cut off in GridField
- Dark overlay (rgba 0,0,0,0.8) with centered enlarged image
- Click anywhere on overlay to close
- Hover cursor indicates clickability

## Testing
- Tested with 5 element templates (3-column cards, hero+CTA, FAQ, two-column, product detail)
- Click-to-enlarge working in all templates
- No navigation conflicts with GridField row clicks

Resolves issue where preview images were cut off making it difficult for editors to see full template layouts.
