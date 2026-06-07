<h1 style="text-align: center;">ADBMS Project: InvenTech</h1>

<p style="text-align: center; font-size: 18px"><b>Project Description</b></p>

InvenTech is an <b> Inventory Management System</b> made specifically for <b>Appliance Stores</b>. It offers core features depending on the store's needs, such as <b>Inventory</b>, <b>Appliances</b>, <b>Categories</b>, <b>Zones and Shelves</b>, <b>Transactions</b>, <b>Alerts</b>, and <b>Reports</b>.

<p><b>Note: </b>This project only works in an offline environment for now. Updates might be made in the future.</p>

The development of this project was assisted by <b><a href="claude.ai">Claude AI</a></b>. All of the designs, concepts, and functions were researched and made entirely by the team.

This project has been proposed and reviewed for a final project on ADBMS course.


<h3 style="text-align: center;">Features</h3><hr>
<br>
<ul>
    <li><b>Inventory</b> - for a quick overview of the inventory and transactions</li>
    <li><b>Appliances</b> - view, add, edit, and delete appliances from the inventory.</li>
    <li><b>Categories</b> - categorize appliances for easy organization.</li>
    <li><b>Zones and Shelves</b> - assign appliances to specific zones and shelves.</li>
    <li><b>Transactions</b> - stock in and out of units.</li>
    <li><b>Alerts</b> - notify users for any potential inventory issues.</li>
    <li><b>Reports</b> - view all data in a printable format.</li>
    <li><b>Settings</b> - system settings to manage users, check system information, backup and restore data, and more.</li>
</ul>

<br>

<h3 style="text-align: center;">Changelogs</h3><hr>
<p>Changes weren't shown early in the commits history, GitHub was used late in the development process. But here are the changelogs:</p>
<ul>
    <li><b>v1.8.4</b> (Patch) - Integrated custom confirmation modals for Clear Image Cache in Danger Zone.</li>
    <li><b>v1.8.3</b> (Patch) - Added Edit Zone and Edit Shelf functionality; UI refinements for shelf headers.</li>
    <li><b>v1.8.2</b> (Patch) - Redesigned CSV Restore flow with drag-and-drop support and sticky settings navigation.</li>
    <li><b>v1.8.1</b> (Patch) - Refined Dashboard charts and updated server references to Laragon.</li>
    <li><b>v1.8.0</b> (Minor) - Alerts system overhaul: archiving, bulk actions, and trigger bug fixes.</li>
    <li><b>v1.7.4</b> (Patch) - Improved audit logging for CSV operations.</li>
    <li><b>v1.7.3</b> (Patch) - UI consistency updates for CSV restore buttons.</li>
    <li><b>v1.7.2</b> (Patch) - Refined Backup/Restore interface.</li>
    <li><b>v1.7.1</b> (Patch) - Fixed Full Reset backend behavior and activity logging.</li>
    <li><b>v1.7.0</b> (Minor) - Added CSV Backup/Restore system and high-quality printable reports.</li>
    <li><b>v1.6.0</b> (Minor) - Zones page overhaul with detailed modal views and shelf management.</li>
    <li><b>v1.5.0</b> (Minor) - Added sortable column headers to the Appliances table.</li>
    <li><b>v1.4.2</b> (Patch) - Fixed custom confirmation dialog forms for archive/restore actions.</li>
    <li><b>v1.4.1</b> (Patch) - Bug fixes for Appliance modals and image preview ratios.</li>
    <li><b>v1.4.0</b> (Minor) - Item photo uploads, auto-SKU generation, and role-based color themes.</li>
    <li><b>v1.3.0</b> (Minor) - Initial implementation of photos and migration to Laragon.</li>
    <li><b>v1.2.5</b> (Patch) - Global replacement of native browser confirms with custom modals.</li>
    <li><b>v1.2.4</b> (Minor) - Advanced ADBMS features: Triggers, Stored Procedures, and Views.</li>
    <li><b>v1.2.3</b> (Patch) - Security audit: implemented prepared statements across all files.</li>
    <li><b>v1.2.2</b> (Minor) - Added Category management and Danger Zone reset tools.</li>
    <li><b>v1.2.1</b> (Patch) - Added version history tracking in System Info.</li>
    <li><b>v1.2.0</b> (Minor) - Major bug fix release for unit counts and CRUD operations.</li>
    <li><b>v1.1.4</b> (Patch) - Added Archived Items section with restoration capability.</li>
    <li><b>v1.1.3</b> (Patch) - Added Profile dropdown menu to Topbar.</li>
    <li><b>v1.1.2</b> (Patch) - Documented XAMPP shutdown procedures to prevent corruption.</li>
    <li><b>v1.1.1</b> (Patch) - Completed initial development of all core system pages.</li>
    <li><b>v1.1.0</b> (Minor) - Started PHP backend development and session management.</li>
    <li><b>v1.0.2</b> (Patch) - Finalized Database ERD.</li>
    <li><b>v1.0.1</b> (Patch) - Created core SQL schema and sample data.</li>
    <li><b>v1.0.0</b> (Major) - Initial static HTML prototype and UI design.</li>
</ul>


<br>

<h3 style="text-align: center;">Tools and Technologies Used</h3><hr>

<table>
    <thead>
        <tr>
            <th align="left">Technologies Used</th>
            <th align="left">Description</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>🌐 HTML5</td>
            <td>Creating the structure and layout of the web application.</td>
        </tr>
        <tr>
            <td>🎨 CSS3</td>
            <td>Visual styling, custom themes, and media printing.</td>
        </tr>
        <tr>
            <td>⚡ JavaScript</td>
            <td>For event functions and user interactions, allowing for a dynamic behavior (buttons, modals, etc.).</td>
        </tr>
        <tr>
            <td>🐘 PHP</td>
            <td>Secure CRUD operations, form validation, and user authentication, ensuring data integrity and security.</td>
        </tr>
        <tr>
            <td>🗄️ MySQL</td>
            <td>Relational database management utilizing tables,triggers, views, and stored procedures.</td>
        </tr>
    </tbody>
</table>
<table>
    <thead>
        <tr>
            <th align="left">Other Technologies</th>
            <th align="left">Description</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>🖼️ Figma & Canva</td>
            <td>User interface design and prototyping tool.</td>
        </tr>
        <tr>
            <td>🐘 Laragon</td>
            <td>Local server environment for testing and development. XAMPP Control Panel was also used but errors occurred too often, making a decision to use Laragon.</td>
        </tr>
    </tbody>
</table>

<br>

<h3 style="text-align: center;">About Us: Team Members</h3><hr>
We are students from <b>BSCS 2-2</b> at <b>Cavite State University</b>. We have consistently worked together as a team for every development opportunity provided by our courses and instructors since the very beginning. Special thanks to the following members for their dedication since Day 1.
<p>
    <ul>
        <li><a href="https://www.linkedin.com/in dionesia-adao-5a2399336/" style="font-size: 16px;font-weight: bold; color: white; text-decoration: none;">Nesia</a></li>
        <li><a href="https://www.linkedin.com/in/louiza-joy-be%C3%B1ares-79b6bb260" style="font-size: 16px;font-weight: bold; color: white; text-decoration: none;">Louiza</a></li>
        <li><a href="www.linkedin.com/in/meynard-angelo-mojello-8a2155339" style="font-size: 16px;font-weight: bold; color: white; text-decoration: none;">Meynard</a> ★</li>
        <li><a href="https://www.linkedin.com/in/ghenly-tinapay-568799339" style="font-size: 16px;font-weight: bold; color: white; text-decoration: none;">Ghenly</a></li>
    </ul>
</p>

Finally, we would like to thank <b>Mr. Mark Mina</b>, our ADBMS instructor at Cavite State University, for the opportunity to learn the Advanced Database Management Systems course and for supervising this project.

<br>
<hr>
<br>

<p style="text-align: center;"> ★ Made by <a href="https://github.com/daytonaaax">daytonaaax </a>★</p>