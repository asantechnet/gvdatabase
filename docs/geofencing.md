# Office geofencing and automatic attendance

## Configuration

1. Open **Setup → Offices** and create or edit an office.
2. Select the office point by clicking the OpenStreetMap, dragging the purple marker, or choosing **Use current location**.
3. Set **Office radius (meters)** with the meter input, slider, or orange map handle.
4. Enable automatic attendance and save the office.
5. Assign employees to that office. Employees also need the existing `manage_own_workday` permission.

Coordinates and radius are stored in SI meters. The server calculates the great-circle distance; it never accepts a client-supplied inside/outside decision.

## Runtime behavior

- While the authenticated application is open and visible, the browser requests a fresh high-accuracy location every 30 seconds.
- Entering the configured radius creates the employee's existing daily attendance record and check-in, idempotently.
- A location more than 100 meters inaccurate is ignored.
- The GPS uncertainty band around the circle is treated as indeterminate.
- Leaving the radius requires two accurate outside observations at least two minutes apart before checkout. Returning inside clears the pending exit.
- After an automatic checkout, the profile menu still requests the required end-of-day report.

Automatic browser tracking requires a secure context (HTTPS, except browser-supported localhost development). Browsers stop page-based location polling after the app is closed or suspended; continuous operating-system background tracking would require a native mobile application or managed device service.

OpenStreetMap tiles require outbound browser access to `https://tile.openstreetmap.org` and retain the required map attribution.
