<?php
require_once 'config.php'; // Include the database configuration file

$selected_month = $selected_year = $selected_room = "";
$rooms = [];
$months = [];
$years = [];

// Fetch distinct rooms, months, and years from the database for dropdown
$roomQuery = "SELECT DISTINCT Room_number FROM users ORDER BY Room_number";
$monthQuery = "SELECT DISTINCT month FROM bill ORDER BY month";
$yearQuery = "SELECT DISTINCT year FROM bill ORDER BY year";

if ($roomResult = $conn->query($roomQuery)) {
    while ($row = $roomResult->fetch_assoc()) {
        $rooms[] = $row['Room_number'];
    }
    $rooms[] = "ทั้งหมด"; // Add the option for "ทั้งหมด"
}
if ($monthResult = $conn->query($monthQuery)) {
    while ($row = $monthResult->fetch_assoc()) {
        $months[] = $row['month'];
    }
}
if ($yearResult = $conn->query($yearQuery)) {
    while ($row = $yearResult->fetch_assoc()) {
        $years[] = $row['year'];
    }
}

// Handle the form submission
$records = [];
$total_sum = 0;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selected_year = $_POST['selected_year'];

    if (isset($_POST['view_monthly_bill'])) {
        $selected_month = $_POST['selected_month'];

        // Fetch the latest bill for each room in the selected month and year
        $sql = "SELECT b.*, u.Room_number, u.First_name, u.Last_name, 
                       CASE 
                           WHEN u.Room_number IN ('201', '202', '302', '303', '304', '305', '306', '203', '204', '205', '206', '301') THEN u.water_was 
                           WHEN u.Room_number = 'S1' THEN b.water_cost 
                           ELSE b.water_cost 
                       END as water_cost_display
                FROM bill b
                LEFT JOIN users u ON b.user_id = u.id
                WHERE b.month = ? AND b.year = ? AND u.Room_number IN ('201', '202', '302', '303', '304', '305', '306', '203', '204', '205', '206', '301', 'S1', 'S2')
                AND (u.Room_number, b.id) IN (
                    SELECT u.Room_number, MAX(b.id)
                    FROM bill b
                    LEFT JOIN users u ON b.user_id = u.id
                    WHERE b.month = ? AND b.year = ?
                    GROUP BY u.Room_number
                )
                ORDER BY u.Room_number";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $selected_month, $selected_year, $selected_month, $selected_year);
    } elseif (isset($_POST['view_yearly_bill'])) {
        $selected_room = $_POST['selected_room'];

        // Fetch the latest bill for each room in each month of the selected year
        if ($selected_room == "ทั้งหมด") {
            $sql = "SELECT b.*, u.Room_number, u.First_name, u.Last_name, 
                           CASE 
                               WHEN u.Room_number IN ('201', '202', '302', '303', '304', '305', '306', '203', '204', '205', '206', '301') THEN u.water_was 
                               WHEN u.Room_number = 'S1' THEN b.water_cost 
                               ELSE b.water_cost 
                           END as water_cost_display
                    FROM bill b
                    LEFT JOIN users u ON b.user_id = u.id
                    WHERE b.year = ? AND u.Room_number IN ('201', '202', '302', '303', '304', '305', '306', '203', '204', '205', '206', '301', 'S1', 'S2')
                    AND (u.Room_number, b.month, b.id) IN (
                        SELECT u.Room_number, b.month, MAX(b.id)
                        FROM bill b
                        LEFT JOIN users u ON b.user_id = u.id
                        WHERE b.year = ?
                        GROUP BY u.Room_number, b.month
                    )
                    ORDER BY u.Room_number, b.month";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $selected_year, $selected_year);
        } else {
            $sql = "SELECT b.*, u.Room_number, u.First_name, u.Last_name, 
                           CASE 
                               WHEN u.Room_number IN ('201', '202', '302', '303', '304', '305', '306', '203', '204', '205', '206', '301') THEN u.water_was 
                               WHEN u.Room_number = 'S1' THEN b.water_cost 
                               ELSE b.water_cost 
                           END as water_cost_display
                    FROM bill b
                    LEFT JOIN users u ON b.user_id = u.id
                    WHERE b.year = ? AND u.Room_number = ?
                    AND (u.Room_number, b.month, b.id) IN (
                        SELECT u.Room_number, b.month, MAX(b.id)
                        FROM bill b
                        LEFT JOIN users u ON b.user_id = u.id
                        WHERE b.year = ? AND u.Room_number = ?
                        GROUP BY u.Room_number, b.month
                    )
                    ORDER BY u.Room_number, b.month";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisi", $selected_year, $selected_room, $selected_year, $selected_room);
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
        $total_sum += $row['total_cost']; // Add each room's total cost to the total sum
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สรุปยอด</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { width: 80%; margin: auto; padding: 20px; background: #f4f4f4; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        form { margin-bottom: 20px; }
        label { margin-right: 10px; }
        select, button { padding: 5px; }
        .total { margin-top: 20px; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h1>เลือกข้อมูลสำหรับการสรุปยอด</h1>
    <h2>สรุปยอดรายเดือน</h2>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <label for="selected_month">เดือน:</label>
        <select id="selected_month" name="selected_month" required>
            <?php foreach ($months as $month): ?>
                <option value="<?php echo $month; ?>" <?php echo $month == $selected_month ? 'selected' : ''; ?>><?php echo $month; ?></option>
            <?php endforeach; ?>
        </select>
        <label for="selected_year">ปี:</label>
        <select id="selected_year" name="selected_year" required>
            <?php foreach ($years as $year): ?>
                <option value="<?php echo $year; ?>" <?php echo $year == $selected_year ? 'selected' : ''; ?>><?php echo $year; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="view_monthly_bill">ดูสรุปยอดรายเดือน</button>
    </form>

    <h2>สรุปยอดรายปี</h2>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <label for="selected_room">หมายเลขห้อง:</label>
        <select id="selected_room" name="selected_room" required>
            <?php foreach ($rooms as $room): ?>
                <option value="<?php echo $room; ?>" <?php echo $room == $selected_room ? 'selected' : ''; ?>><?php echo $room; ?></option>
            <?php endforeach; ?>
        </select>
        <label for="selected_year">ปี:</label>
        <select id="selected_year" name="selected_year" required>
            <?php foreach ($years as $year): ?>
                <option value="<?php echo $year; ?>" <?php echo $year == $selected_year ? 'selected' : ''; ?>><?php echo $year; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="view_yearly_bill">ดูสรุปยอดรายปี</button>
    </form>

    <?php if (!empty($records)): ?>
        <h2>ผลลัพธ์:</h2>
        <table>
            <tr>
                <th>หมายเลขห้อง</th>
                <th>เดือน</th>
                <th>ปี</th>
                <th>ค่าไฟฟ้า</th>
                <th>ค่าน้ำ</th>
                <th>ค่าห้อง</th>
                <th>ค่าใช้จ่ายทั้งหมด</th>
            </tr>
            <?php foreach ($records as $record): ?>
                <tr>
                    <td><?php echo htmlspecialchars($record['Room_number']); ?></td>
                    <td><?php echo htmlspecialchars($record['month']); ?></td>
                    <td><?php echo htmlspecialchars($record['year']); ?></td>
                    <td><?php echo htmlspecialchars($record['electric_cost']); ?> บาท</td>
                    <td><?php echo htmlspecialchars($record['water_cost_display']); ?> บาท</td>
                    <td><?php echo htmlspecialchars($record['room_cost']); ?> บาท</td>
                    <td><?php echo htmlspecialchars($record['total_cost']); ?> บาท</td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th colspan="6" style="text-align: right;">ยอดรวมทั้งหมด:</th>
                <th><?php echo htmlspecialchars(number_format($total_sum, 2)); ?> บาท</th>
            </tr>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
