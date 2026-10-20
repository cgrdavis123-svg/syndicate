# Icons

Pages use `{{icon:name}}` (decorative, next to a text label) or `{{icon:name|Label}}` (stands in for the label). The builds inline the SVG, so there are no extra requests and the CSP needs no changes.

- `ui/` holds our own simple line icons (eye, cart, chat, calendar…). They use `currentColor`. Add more in the same style: 16×16 viewBox, 1.5 stroke, round caps.
- `brands/` holds other companies' logos. **Use only the official files each company publishes**, unedited apart from picking the version (full color or one-color) their guidelines allow, and follow their spacing and sizing rules. Until a file is there, `{{icon:name|Label}}` shows the label as text and the build prints a reminder, so nothing breaks.

## Brand files to add

Save each as `icons/brands/<name>.svg`:

| File | Where it's used | Get it from |
|---|---|---|
| `visa.svg`, `mastercard.svg`, `amex.svg`, `discover.svg` | syndicatemarketing.co footer (cards we accept) | each network's merchant / brand center, or Stripe's payment method icons |
| `apple-pay.svg` | footer | Apple Pay marketing guidelines (Apple Pay mark) |
| `google-pay.svg` | footer | Google Pay brand guidelines (acceptance mark) |
| `stripe.svg` | footer ("Payments by Stripe") | Stripe's brand assets page |
| `google.svg`, `meta.svg`, `tiktok.svg` | ad platform cards, checkout, chips, stusyn.com channels | Google, Meta and TikTok brand guidelines |
| `discord.svg` | community and gaming pages | Discord's brand / press kit page |

Each card network's rules say to show its mark at equal size with the others and not to alter it; the footer CSS keeps them all the same height.
