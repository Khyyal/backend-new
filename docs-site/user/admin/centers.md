# Centers

## Creating and reviewing centers

Centers register themselves, or you can create one from **Centers → New**. Each center has a **state** that you control and a **status** that the center controls.

| | Values | Who changes it |
|---|---|---|
| **State** | **Pending**, **Active**, **Blocked** | Admins only |
| **Status** | **Visible**, **Invisible** | The center (admins can change it too) |

- A center must be **Active** before its team can use the dashboard.
- Clients only see centers that are **Visible and Active**.
- **Blocked** stops the center from operating. Its data is kept.

Only admins can change state, status, custom site and domain.

The centers list shows the newest centers first. You can sort it by **Center** (name), **Points** or **Created**; see [Lists](./getting-around#lists).

Deleted centers are hidden from the list. You can filter the list to include them or to show only deleted centers, and open a deleted center to see its details. A deleted center cannot be edited.

With the **centers.restore** permission you can **restore** a deleted center. It comes back as it was, with the same state, status, owner and team, and their permissions. Team members whose accounts were removed with the center can sign in again. Centers deleted before this was possible come back without a team.

**Points** decide how high a center appears when clients browse the most popular centers. Admins can set a center's points when editing it; the center's team cannot.

## What you can open for a center

From a center you can open (and, with the right permission, edit) the same things its team uses:

- **Services** of every type, and trainers, service blocks and periods
- **Reservations**, including creating, cancelling, completing, marking no-show and rescheduling on its behalf
- **Purchases** and the center's **ledger**
- **Clients**, **blocked clients**, **packages** and **client memberships**
- **Horses** kept at the center
- **Ratings**, newest first or highest first
- **Subscription**, social links and site theme

## Managing a center's users

The **Users** tab of a center lists its team: the primary user (the owner) first, then each staff member with their status, linked trainer and permissions. With the **centers.update** permission you can:

- **Add** a user with a name, phone, linked trainer, status and any permissions. If the phone already has an account, that account is added and keeps its name.
- **Edit** a user's name, phone, trainer, status and permissions. A new phone must be verified again and the user is signed out on every device.
- **Remove** a user. They lose access to the center; an account left without any center is deleted.

For the primary user you can only change the name and phone. The primary user always has every permission and cannot be removed.

## Generating a login code for a center user

If someone on a center's team is not receiving the SMS, open the center, choose **Generate OTP**, and pick the user. You get a new 4-digit code to read to them. No SMS is sent, the code is shown only once, and it expires after 5 minutes. This needs the **otps.create** permission.

## Cities and tags

Manage the **cities** (with their coverage radius) and **tags** that centers choose from, under **Cities** and **Tags**.

## Exporting

You can export the centers list, and a center's purchases and ledger. See [Analytics and exports](./analytics).
