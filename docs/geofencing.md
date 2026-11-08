# Office geofencing and automatic attendance

## Configuration

1. Open **Setup → Offices** and create or edit an office.
2. Select the office point by clicking the OpenStreetMap, dragging the purple marker, or choosing **Use current location**.
3. Set **Office radius (meters)** with the meter input, slider, or orange map handle.
4. Enable automatic attendance and save the office.
5. Assign employees to that office. Employees also need the existing `manage_own_workday` permission.

Coordinates and radius are stored in SI meters. The server calculates the great-circle distance; it never accepts a client-supplied inside/outside decision.

## Runtime behavior

- Start my day and End my day are the only actions that record punches. Background tracking does not create or close a day.
- Each of them requests one fresh high-accuracy location after the employee clicks, while the application is open and visible.
- The position must be inside the configured radius, the reported accuracy must be at most 100 meters, and the GPS uncertainty band is treated as indeterminate.
- Checkout verifies the same fence that admitted the shift and stores its own coordinates and distance.
- A workday that started without a fence (fence disabled, no office fence, or automatic attendance turned off) still ends without a location, so an open day is never trapped.

Automatic browser tracking requires a secure context (HTTPS, except browser-supported localhost development). Browsers stop page-based location polling after the app is closed or suspended; continuous operating-system background tracking would require a native mobile application or managed device service.

OpenStreetMap tiles require outbound browser access to `https://tile.openstreetmap.org` and retain the required map attribution.
