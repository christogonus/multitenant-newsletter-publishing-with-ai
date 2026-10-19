# How to Add an Order Bump to Any Product

An order bump is the extra "add this for $X" box a buyer ticks on a checkout. Every product can have one: the Front End and each OTO (OTO 1, OTO 2 Silver, OTO 2 Gold, OTO 3 to OTO 12). A bump can be a PDF book, a set of images, or both, and it can also switch on a built-in feature.

## Before you start: how the bump reaches NewsPub

LaunchpadJV only tells NewsPub **that** the buyer ticked the bump, not **which** bump it was. So NewsPub gives the buyer the bump that is **live** on that product in Admin. That is why:

- each product has **one live bump** at a time, and
- the bump must be set up in NewsPub **before** buyers can take it.

If a buyer takes a bump on a product with no live bump, the sale still goes through, and every admin gets an email asking you to set one up and give it to that buyer by hand (see "Give a bump by hand" below).

## Step 1: Turn on the bump in LaunchpadJV

On the LaunchpadJV checkout of the product (for example OTO 3), add the order bump with its name and price. Note the price: you need the same number in Step 2.

No new product ID or `.env` setting is needed. The bump arrives on the same sale as the product.

## Step 2: Create the bump in NewsPub

1. Sign in with your admin account, open the account menu (top right) and click **Admin**.
2. Click **Order bumps** at the top of the page.
3. Click **Add a bump**. A panel opens on the right.
4. Fill in:

   | Field | What to enter |
   |---|---|
   | **Sold on the checkout of** | The product whose checkout shows this bump, for example *Subscriber Growth Engine* for OTO 3. For OTO 2, Silver and Gold are separate checkouts: set up a bump on each if both offer one. |
   | **Name** | The name buyers saw on the checkout, for example *100 Instagram Templates*. Buyers see it on their Downloads page. |
   | **Description** | Optional. A line shown to buyers on their Downloads page. |
   | **Price ($)** | **Exactly** the bump price on the LaunchpadJV checkout. NewsPub uses it to split the checkout total between the product and the bump, which keeps refunds and reports right. |
   | **Also unlocks** | Usually **Nothing, files only**. Pick a product only if the bump is a built-in feature, such as the Niche and Swipe Vault. |

5. Click **Save**. If the product had no live bump, this one goes live straight away.

## Step 3: Upload the files

1. In the same panel, scroll to **Files buyers download**.
2. Click the file picker and choose your files. You can pick up to 50 at once (hold Ctrl or Shift).
3. Click **Upload**. The files appear in the list; images show a thumbnail.

Allowed: **PDF, PNG, JPG, WebP, GIF and zip**, up to **100 MB each**. Other types (such as SVG) are refused. Large files need the server's upload limits raised to 100 MB (see `DEPLOY-CHECKLIST.md`, section 1).

To remove a file, click the bin icon next to it. Buyers stop seeing it at once. Files you add later show up for earlier buyers too.

## Step 4: Check it

- In **Admin → Order bumps**, the bump shows **Live** next to its product, with the number of files. A **Delivers nothing yet** warning means it has no files and unlocks nothing: upload the files before buyers take it.
- Make a test purchase with the bump ticked, or give the bump to a demo account (below). The buyer sees **Downloads** in their sidebar, with PDFs as a list, images as a grid, and a **Download all** button that gets everything as one zip.

## Repeat for each product

Do Steps 1 to 4 for every product that has a bump on its checkout:

| Product | Pick under "Sold on the checkout of" |
|---|---|
| Front End | NewsPub AI |
| OTO 1 | Delivery and Platform Integrations |
| OTO 2 Silver | Unlimited Creation Silver |
| OTO 2 Gold | Unlimited Creation Gold |
| OTO 3 | Subscriber Growth Engine |
| OTO 4 | Sponsor and Payday Vault |
| OTO 5 | Done-For-You Empire |
| OTO 6 | Social Repurposing |
| OTO 7 | Content Autopilot |
| OTO 8 | Premium Designs and Domain |
| OTO 9 | Multi-Newsletter Manager |
| OTO 10 | Cross-Promo Network |
| OTO 11 | Agency Client License |
| OTO 12 | Reseller License |

## Changing a product's bump later

Add the new bump (Step 2), then click **Make live** on it. The old bump on that product switches off automatically. Buyers who already bought the old one keep their downloads; new buyers get the new one.

**Delete** removes a bump nobody has bought. A bump that has buyers is switched off instead, so they keep their files.

## Give a bump by hand

For a buyer who missed out (for example, the bump was taken before it was set up), or to test with a demo account:

1. **Admin → Buyers**, find the buyer and click **Open**.
2. Under **Grant a product or order bump**, choose **Order bump: (name) (product)** and click **Grant**.

To take it back, click **Revoke** next to it in that buyer's ledger.

## Refunds

When LaunchpadJV refunds or charges back a sale, NewsPub takes back the product **and** its bump from that sale. The buyer loses access to the bump files immediately.
