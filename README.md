# Merlin_CheckoutProbe

## V1.0.3 Fixed Extension
Fixed extension, added easier command line reporting, and checks for more log files 

## Overview

`Merlin_CheckoutProbe` is a Magento 2 diagnostic module built to trace and investigate intermittent checkout failures, with a particular focus on Klarna checkout issues such as:

- customer being returned to cart after payment
- missing authorization token/session data
- unexpected redirects during checkout flow
- hidden frontend/controller exceptions
- inconsistent behaviour between theme overrides and core payment templates

The module runs quietly in the background and records structured checkout events so failed payment attempts can be investigated after the fact.

---

## Features

### 1. Redirect logging
Logs important frontend redirects during checkout, especially cases such as:

- redirect back to cart
- redirect to checkout
- redirect to success page
- redirect loops or unexpected payment step jumps

This makes it easier to identify whether Magento is redirecting the shopper away from the expected Klarna flow.

### 2. Front controller exception capture
Captures unhandled exceptions thrown during request dispatch and records useful request context without breaking the frontend.

Typical examples include:

- controller exceptions
- quote/session lookup failures
- bad AJAX calls during checkout
- payment endpoint failures

### 3. Structured event storage
Checkout probe events are written into the database table:

- `merlin_checkoutprobe_event`

This allows correlation of failures by time window, request path, quote, masked quote ID, and event type.

### 4. Dedicated probe log
Writes additional debug information to:

- `var/log/merlin_checkout_probe.log`

This log is useful when reviewing live request flow during payment testing.

### 5. Response body capture
Where useful and safe, the module can record response body snippets for failing endpoints to help identify:

- backend JSON errors
- HTML exception pages
- AJAX endpoint failures
- payment authorize/update endpoint responses

### 6. Klarna diagnostics
The module has been extended to help inspect Klarna-specific failures by checking:

- Klarna-related events captured during checkout
- `klarna_logs` table contents when present
- `var/log/klarna.log`
- failures around authorization token update / session initialization / callback flow

### 7. Failure-window reporting
Includes CLI reporting tools to inspect what happened around a failed checkout attempt.

This is especially useful for investigating "customer paid but got sent back to cart" scenarios.

---

## CLI Commands

### Enable probe
```bash
bin/magento merlin:checkoutprobe:enable
```

Enables the module’s diagnostic logging.

### Disable probe
```bash
bin/magento merlin:checkoutprobe:disable
```

Disables the probe logging without uninstalling the module.

### Purge probe events
```bash
bin/magento merlin:checkoutprobe:purge
```

Clears old probe data from the module’s event table.

### Generate failure report
```bash
bin/magento merlin:checkoutprobe:report --minutes=30
```

Generates a report for the recent failure window.

Depending on the implementation currently installed, this report can include:

- probe events from `merlin_checkoutprobe_event`
- redirects back to cart/success
- recent exceptions from Magento logs
- matched quote / masked quote references
- Klarna log entries from `var/log/klarna.log`
- Klarna DB log rows from `klarna_logs` if available
- related entries from `var/log/exception.log`, `var/log/system.log`, and `var/log/debug.log`
- entries from `var/log/merlin_checkout_probe.log`

If your current command supports more options such as quote filtering or custom time windows, use:

```bash
bin/magento merlin:checkoutprobe:report --help
```

---

## What the module is designed to catch

`Merlin_CheckoutProbe` is intended to help diagnose issues such as:

- Klarna payment completes externally but Magento returns shopper to cart
- AJAX checkout endpoints returning 500 or malformed JSON
- missing quote/cart ID during payment callbacks
- authorization token not being persisted correctly
- session mismatch between checkout steps
- third-party extension interference during payment rendering or callback handling
- theme overrides breaking payment method templates
- checkout controllers returning unexpected results

---

## Example use during Klarna testing

1. Enable the probe:
```bash
bin/magento merlin:checkoutprobe:enable
```

2. Run a checkout test with Klarna.

3. If checkout fails or returns to cart, immediately run:
```bash
bin/magento merlin:checkoutprobe:report --minutes=15
```

4. Review:
- `var/log/merlin_checkout_probe.log`
- `var/log/klarna.log`
- Magento exception/system logs
- database rows in `merlin_checkoutprobe_event`

This helps isolate exactly what happened during the failure window.

---

## Notes from this implementation

During investigation, the probe helped identify that checkout instability was not caused only by backend Klarna session handling. A frontend theme override of the Klarna payment template was also involved.

In particular, the overridden file:

- `amasty/theme-frontend-jet-theme-lite/Klarna_Kp/web/template/payments/kp.html`

was found to differ from the vendor Klarna template and could affect the checkout method UI and payment flow.

That means this module is useful not only for backend exceptions, but also for proving whether the root cause is:

- controller/backend logic
- AJAX endpoint failure
- quote/session corruption
- third-party extension conflict
- theme/template override mismatch

---

## Database table

Main event table used by the extension:

- `merlin_checkoutprobe_event`

Depending on the installed revision, this table stores event type plus payload/context JSON for each captured checkout event.

---

## Log files to review

Primary:
- `var/log/merlin_checkout_probe.log`

Magento core:
- `var/log/exception.log`
- `var/log/system.log`
- `var/log/debug.log`

Klarna:
- `var/log/klarna.log`

Optional database source:
- `klarna_logs`

---

## Recommended workflow

For live debugging:

1. Enable probe
2. Reproduce one checkout failure
3. Run report command immediately
4. Match timestamps across:
   - probe log
   - Magento logs
   - Klarna log
   - probe DB events
5. Fix one issue at a time
6. Retest with probe still enabled
7. Disable probe once stable

---

## Production guidance

This module is intended as a diagnostic tool. It can be left installed, but should generally only be enabled when actively investigating checkout problems.

Reasons:

- it increases logging volume
- it may capture additional request/response diagnostics
- it is best used during focused checkout testing windows

When not in use:

```bash
bin/magento merlin:checkoutprobe:disable
```

---

## Summary

`Merlin_CheckoutProbe` gives you a structured way to investigate intermittent Magento checkout failures by combining:

- redirect tracing
- exception capture
- response inspection
- database event logging
- Klarna-specific diagnostics
- CLI reporting over failure windows

It was built specifically to stop “random” checkout failures from staying random.

