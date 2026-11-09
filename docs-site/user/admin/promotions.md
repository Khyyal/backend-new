# Promotions, campaigns and coupons

## Promotions

A promotion is a discount rule. Create it from **Marketing → Promotions → New**. It starts as a **draft** and does nothing until you **activate** it.

### Main choices

| Setting | Options |
|---|---|
| **Scope** | **Item:** each matching service line. **Order:** the whole purchase. |
| **Discount** | **Percentage**, **fixed amount** off, or **fixed price** (the item becomes this price) |
| **Maximum discount** | A cap, useful with percentages |
| **Minimum spend** | The eligible items must reach this amount |
| **Center** | One center, or empty for a platform-wide offer |
| **Funded by** | **Platform**, **center**, or **shared** (with the center's share) |
| **Stacking** | **Stackable** combines with other offers. **Exclusive** does not. |
| **Priority** | Breaks ties between two equally good offers |
| **Dates** and **weekly schedule** | When it runs |
| **Targets** | Which services it includes or excludes |
| **Needs a coupon** | Applies only when the client enters a code |

### Lifecycle

**Draft → Active ⇄ Paused → Archived.** You can pause and resume. An archived promotion is read-only.

Once a promotion has been used in a purchase, its scope, discount, maximum and targets are **locked**. To change them, archive it and create a new one.

### How offers combine

- Each line gets **at most one item offer**: the biggest discount wins.
- **At most one order offer** is added on top.
- An **exclusive** offer stops other offers from stacking with it.
- A discount never goes below zero.

## Campaigns

A campaign groups promotions under one marketing banner (for example a season). It can be featured on the [homepage](./homepage).

## Coupons

Coupons unlock a promotion that needs a code. Create them one by one or **generate** a batch. Codes are saved in capitals. You can limit total uses and uses per client, and **assign** a coupon to specific clients.

Clients see the offer in the app only while it is running and has uses left.

## Seeing results

Open a promotion's **Redemptions** to see where it was used, and **Analytics → Promotions** for totals.
