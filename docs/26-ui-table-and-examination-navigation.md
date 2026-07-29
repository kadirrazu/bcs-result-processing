# UI Table and Examination Navigation — v1.7

The application shell is divided into editable partials for the topbar, main navigation, user menu, examination navigation, flash messages, and footer.

The secondary processing menu is rendered only when `ExaminationContext::current()` returns an active examination. Its items are maintained in `config/navigation.php`. Assign a named route when a processing module is implemented; unavailable routes stay disabled instead of producing broken links.

The global paginator now uses `resources/views/vendor/pagination/tabler.blade.php`. It intentionally omits Laravel's duplicate result summary. List pages provide one summary through `.app-table-summary` and one pagination control through `.app-pagination`.
