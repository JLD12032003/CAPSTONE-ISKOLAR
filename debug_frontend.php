<?php
require_once 'config/database.php';
require_once 'app/models/Scholarship.php';

$scholarshipModel = new Scholarship();
$scholarships = $scholarshipModel->getActiveScholarships(6);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Frontend</title>
    <script>
        function debugScholarshipData() {
            const scholarships = <?= json_encode($scholarships); ?>;
            console.log('Scholarships from PHP:', scholarships);
            
            scholarships.forEach(scholarship => {
                console.log(`ID: ${scholarship.id}, Title: ${scholarship.title}`);
            });
            
            // Test viewScholarship function
            if (scholarships.length > 0) {
                console.log('Testing with first scholarship ID:', scholarships[0].id);
                viewScholarship(scholarships[0].id);
            }
        }
        
        function viewScholarship(scholarshipId) {
            console.log('viewScholarship called with ID:', scholarshipId);
            
            const scholarships = <?= json_encode($scholarships); ?>;
            const scholarship = scholarships.find(s => s.id == scholarshipId);
            
            console.log('Found scholarship:', scholarship);
            
            if (scholarship) {
                // Set scholarship ID for application
                const idField = document.getElementById('applicationScholarshipId');
                if (idField) {
                    idField.value = scholarshipId;
                    console.log('Set applicationScholarshipId to:', scholarshipId);
                } else {
                    console.error('applicationScholarshipId field not found!');
                }
            } else {
                console.error('Scholarship not found for ID:', scholarshipId);
            }
        }
        
        function testFormSubmission() {
            const scholarshipId = document.getElementById('applicationScholarshipId').value;
            console.log('Form would submit with scholarship ID:', scholarshipId);
            
            // Create a test form
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="apply">
                <input type="hidden" name="scholarship_id" value="${scholarshipId}">
                <input type="hidden" name="personal_statement" value="Test statement">
                <input type="hidden" name="why_deserve_scholarship" value="Test reason">
            `;
            
            console.log('Form data:');
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }
        }
    </script>
</head>
<body>
    <h1>Frontend Debug</h1>
    
    <input type="hidden" id="applicationScholarshipId" value="">
    
    <button onclick="debugScholarshipData()">Debug Scholarship Data</button>
    <button onclick="testFormSubmission()">Test Form Submission</button>
    
    <h2>Scholarships from PHP:</h2>
    <pre><?= json_encode($scholarships, JSON_PRETTY_PRINT); ?></pre>
    
    <h2>Available Scholarship IDs:</h2>
    <ul>
        <?php foreach ($scholarships as $scholarship): ?>
            <li>
                ID: <?= $scholarship['id']; ?> - <?= htmlspecialchars($scholarship['title']); ?>
                <button onclick="viewScholarship(<?= $scholarship['id']; ?>)">Test View</button>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>