# TODO - Profile/Auth sync + redirect fixes

- [ ] Read remaining relevant files (profile UI, landing/project/profile related redirect logic, any other pages linking to landing/ profile).
- [ ] Implement single source of truth: replace profile.html localStorage reads with server profile fetch before rendering.
- [ ] Add safe auth check on profile.html load; clear localStorage cache if unauthenticated.
- [ ] Ensure login/register writes verified server profile only (overwrite cache after fetch).
- [ ] Fix logout: clear all profile-related localStorage keys (on client) before redirect.
- [ ] Remove/replace any redirect to non-existent landing/landing.html.
- [ ] Ensure landing/landing.php redirects only when user has no Firestore profile.
- [ ] Test flows: login, logout, switching accounts in same browser, direct navigation to profile/landing.

