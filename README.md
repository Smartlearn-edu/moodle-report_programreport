# Program Report (`report_programreport`) for Moodle

The **Program Report** plugin provides an executive, program-centric matrix view of student performance across academic programs in Moodle. While student-centric reports show a single student across all their courses, `report_programreport` aggregates an entire cohort within an academic program, mapping students against curriculum courses in their defined sequence.

---

## 🌟 Key Features

- **Dual Program Engine Support**: Automatically detects and seamlessly integrates with:
  - **Open LMS Programs** (`enrol_programs`)
  - **MuTMS Programs** (`tool_muprog` / `enrol_muprog`)
- **Curriculum Sequence Preservation**: Automatically loads courses in the exact curriculum order defined in the program items tree (`topitem` and `sequencejson`).
- **High-Performance Batch Preloader**: Completely eliminates the $N \times M$ query bottleneck by preloading all course grade items, student final grades, completions, and enrolments in 4 batch SQL queries.
- **Top KPI Cards**: Displays cohort metrics: Total Enrolled Students, Total Curriculum Courses, Program Completion Rate (%), and Cohort Average Grade.
- **Sticky Matrix Table**: Pin the student name column to the left viewport while scrolling horizontally across dozens of courses.
- **Compact Grade Cells with Tooltips**: Shows score percentages and status badges (Passed, Failed, In Progress, Completed, Not Enrolled) with rich tooltips detailing raw score, max score, pass mark, and completion date.
- **Multi-Format Matrix Exports**:
  - **Excel (.xlsx)**: Formatted workbook with institution banner, metadata, matrix data, and bottom course statistics.
  - **CSV**: Streamed UTF-8 with BOM for instant opening in Microsoft Excel and external BI tools.
  - **Landscape PDF**: Clean, responsive TCPDF landscape document with organization logo and compact typography.
- **Deep Navigation Integration**:
  - Click any student name to open their detailed course grade breakdown in `report_studentgrades`.
  - Click any course column header to open the course homepage.
  - Click any grade badge to open that student's user grader report.
- **Site Administration Branding**: Configure custom header logos and color pickers for headers, badges, and export files in Site Administration.

---

## 📋 Requirements

- **Moodle**: 4.1+ (including 4.2, 4.3, 4.4, 4.5, and 5.x)
- **PHP**: 8.1, 8.2, 8.3, 8.4
- **Program Plugins** (at least one):
  - `enrol_programs` (Open LMS Programs)
  - OR `tool_muprog` (MuTMS Programs)

---

## 🚀 Installation

1. Copy or clone the plugin directory into your Moodle reports folder:
   ```bash
   git clone <repository_url> report/programreport
   ```
2. Log into your Moodle site as an administrator and navigate to **Site administration > Notifications** to complete the database upgrade.
3. Access the report under **Site administration > Reports > Program Report** or via `/report/programreport/index.php`.

---

## 🔒 Permissions & Privacy

- **GDPR Compliant**: Implements `\core_privacy\local\metadata\null_provider` — does not store personal data.
- **Access Control**: Strictly restricted to Site Administrators and managers holding `report/programreport:view` or `report/programreport:viewall`.

---

## 📄 License

Licensed under the [GNU General Public License v3 or later](https://www.gnu.org/licenses/gpl-3.0.html).
Copyright &copy; 2026 Mohammad Nabil (<mohammad@smartlearn.education>).
