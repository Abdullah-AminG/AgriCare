# Screenshot checklist

Screenshots must be taken by running the application in a browser. **None have been captured yet.**
Save each file in this folder using the exact file name below (PNG, about 1280 px wide).

Use the sample accounts from `database/seed.sql`:
farmer `ali@example.com` / `Farmer@123`, admin `admin@agricare.local` / `Admin@123`.
Do not show any real password or personal data.

| Done | File name | Page (log in as) | What to show |
|---|---|---|---|
| [ ] | `home.png` | `/index.php` (not logged in) | Hero section and statistics from the database |
| [ ] | `login.png` | `/login.php` | Login form |
| [ ] | `register.png` | `/register.php` | Registration form (optionally with a validation error) |
| [ ] | `farmer-dashboard.png` | `/farmer/dashboard.php` (Ali) | Statistic cards, recent reports, latest advice |
| [ ] | `farms.png` | `/farmer/farms.php` (Ali) | Add-farm form and farm table |
| [ ] | `crops.png` | `/farmer/crops.php` (Ali) | Crop records with Edit and Delete buttons |
| [ ] | `crop-edit.png` | `/farmer/crop_edit.php?id=1` (Ali) | Planting-date edit form |
| [ ] | `report-new.png` | `/farmer/report_new.php` (Ali) | Report form with the photo field |
| [ ] | `report-details.png` | `/farmer/report_view.php?id=1` (Ali) | Status, adviser advice, possible disease, follow-up notes |
| [ ] | `admin-dashboard.png` | `/admin/dashboard.php` (admin) | Statistics, pending reports, reviewer activity |
| [ ] | `admin-reports.png` | `/admin/reports.php` (admin) | Search and filter bar with results |
| [ ] | `report-review.png` | `/admin/review.php?id=2` (admin) | Farmer details, symptoms, advice form |
| [ ] | `guide.png` | `/guide.php` | Crop and disease guide with search |

When the files exist, paste this into `README.md` (section "Screenshots"):

```markdown
![Home page](docs/screenshots/home.png)
![Login](docs/screenshots/login.png)
![Farmer dashboard](docs/screenshots/farmer-dashboard.png)
![Farm management](docs/screenshots/farms.png)
![Crop management](docs/screenshots/crops.png)
![Report submission](docs/screenshots/report-new.png)
![Report details](docs/screenshots/report-details.png)
![Administrator dashboard](docs/screenshots/admin-dashboard.png)
![Report review](docs/screenshots/report-review.png)
```
