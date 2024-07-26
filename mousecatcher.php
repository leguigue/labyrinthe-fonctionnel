<?php

require_once '_db/dbconnect.php';
$stmt = $conn->prepare("SELECT pseudo FROM user WHERE id = :id");
$stmt->bindParam(':id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
// $pseudo = htmlspecialchars($user['pseudo'], ENT_QUOTES, 'UTF-8');
// Function to display the maze
function displayMaze($maze, $fogOfWar, $catPosition)
{
    echo "<table>";
    for ($i = 0; $i < count($maze); $i++) {
        echo "<tr>";
        for ($j = 0; $j < count($maze[$i]); $j++) {
            echo "<td>";
            if (!$fogOfWar[$i][$j]) {
                if ($i == $catPosition['row'] && $j == $catPosition['col']) {
                    echo "🐱";
                } elseif ($maze[$i][$j] == '🧱') {
                    echo "🧱";
                } elseif ($maze[$i][$j] == '🐭') {
                    echo "🐭";
                } else {
                    echo "⬜";
                }
            } else {
                echo "🌫️";
            }
            echo "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

function findRandomMousePosition($maze)
{
    $secondRow = $maze[1];
    $validPositions = [];
    for ($col = 0; $col < count($secondRow); $col++) {
        if ($secondRow[$col] !== '🧱') {
            $validPositions[] = $col;
        }
    }
    if (empty($validPositions)) {
        return null; // No valid position found
    }
    $randomCol = $validPositions[array_rand($validPositions)];
    return ['row' => 1, 'col' => $randomCol];
}

function findRandomCatPosition($maze)
{
    $lastRowIndex = count($maze) - 1;
    $secondToLastRow = $maze[$lastRowIndex - 1];
    $validPositions = [];
    for ($col = 0; $col < count($secondToLastRow); $col++) {
        if ($secondToLastRow[$col] !== '🧱') {
            $validPositions[] = $col;
        }
    }
    if (empty($validPositions)) {
        return null; // No valid position found
    }
    $randomCol = $validPositions[array_rand($validPositions)];
    return ['row' => $lastRowIndex - 1, 'col' => $randomCol];
}

function moveCat(&$maze, &$catPosition, $direction, $mousePosition)
{
    $currentRow = $catPosition['row'];
    $currentCol = $catPosition['col'];
    $newRow = $currentRow;
    $newCol = $currentCol;

    switch ($direction) {
        case 'up':
            $newRow = $currentRow - 1;
            break;
        case 'down':
            $newRow = $currentRow + 1;
            break;
        case 'left':
            $newCol = $currentCol - 1;
            break;
        case 'right':
            $newCol = $currentCol + 1;
            break;
        default:
            echo "direction not valid";
            return false;
    }

    if (isValidMove($maze, $newRow, $newCol)) {
        $catPosition['row'] = $newRow;
        $catPosition['col'] = $newCol;

        // Check if cat caught the mouse
        if ($catPosition['row'] === $mousePosition['row'] && $catPosition['col'] === $mousePosition['col']) {
            return true; // Cat caught the mouse
        }
    } else {
        echo "direction not valid";
    }

    return false; // Mouse not caught
}

function isValidMove($maze, $row, $col)
{
    if ($maze === null || empty($maze)) {
        return false;
    }
    if ($row < 0 || $row >= count($maze) || $col < 0 || $col >= count($maze[0])) {
        return false;
    }
    if ($maze[$row][$col] === '🧱') {
        return false;
    }
    return true;
}

function calculateDistance($point1, $point2)
{
    return abs($point1['row'] - $point2['row']) + abs($point1['col'] - $point2['col']);
}

function getNeighbors($maze, $point)
{
    $neighbors = [];
    $directions = [[-1, 0], [1, 0], [0, -1], [0, 1]];
    foreach ($directions as $dir) {
        $newRow = $point['row'] + $dir[0];
        $newCol = $point['col'] + $dir[1];
        if (isValidMove($maze, $newRow, $newCol)) {
            $neighbors[] = ['row' => $newRow, 'col' => $newCol];
        }
    }
    return $neighbors;
}

function fleeHeuristic($point, $catPosition)
{
    return -calculateDistance($point, $catPosition);
}

function reverseAStar($maze, $start, $catPosition)
{
    $openSet = new SplPriorityQueue();
    $openSet->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
    $openSet->insert($start, 0);

    $cameFrom = [];
    $gScore = [$start['row'] . ',' . $start['col'] => 0];
    $fScore = [$start['row'] . ',' . $start['col'] => fleeHeuristic($start, $catPosition)];

    while (!$openSet->isEmpty()) {
        $current = $openSet->extract()['data'];

        if (calculateDistance($current, $catPosition) >= 3) {
            return reconstructPath($cameFrom, $current);
        }

        foreach (getNeighbors($maze, $current) as $neighbor) {
            $tentativeGScore = $gScore[$current['row'] . ',' . $current['col']] + 1;

            if (!isset($gScore[$neighbor['row'] . ',' . $neighbor['col']]) || $tentativeGScore < $gScore[$neighbor['row'] . ',' . $neighbor['col']]) {
                $cameFrom[$neighbor['row'] . ',' . $neighbor['col']] = $current;
                $gScore[$neighbor['row'] . ',' . $neighbor['col']] = $tentativeGScore;
                $fScore[$neighbor['row'] . ',' . $neighbor['col']] = $tentativeGScore + fleeHeuristic($neighbor, $catPosition);

                $openSet->insert($neighbor, -$fScore[$neighbor['row'] . ',' . $neighbor['col']]);
            }
        }
    }

    return null; // No path found
}

function reconstructPath($cameFrom, $current)
{
    $path = [$current];
    $key = $current['row'] . ',' . $current['col'];
    while (isset($cameFrom[$key])) {
        $current = $cameFrom[$key];
        array_unshift($path, $current);
        $key = $current['row'] . ',' . $current['col'];
    }
    return $path;
}

function moveMouse(&$maze, &$mousePosition, $catPosition)
{
    $path = reverseAStar($maze, $mousePosition, $catPosition);
    if ($path && count($path) > 1) {
        $newPosition = $path[1];
        $maze[$mousePosition['row']][$mousePosition['col']] = '⬜';
        $maze[$newPosition['row']][$newPosition['col']] = '🐭';
        $mousePosition = $newPosition;
    }
}

function updateFogOfWar($maze, $catPosition)
{
    $fogOfWar = array_fill(0, count($maze), array_fill(0, count($maze[0]), true));
    $directions = [[0, 0], [-1, 0], [1, 0], [0, -1], [0, 1]]; // Current position and adjacent cells
    foreach ($directions as $dir) {
        $newRow = $catPosition['row'] + $dir[0];
        $newCol = $catPosition['col'] + $dir[1];
        if ($newRow >= 0 && $newRow < count($maze) && $newCol >= 0 && $newCol < count($maze[0])) {
            $fogOfWar[$newRow][$newCol] = false;
        }
    }
    return $fogOfWar;
}

function randoMaze()
{
    $size = 15;
    $maze = array_fill(0, $size, array_fill(0, $size, '⬜'));
    // Fill the edges with walls
    for ($i = 0; $i < $size; $i++) {
        $maze[0][$i] = '🧱';
        $maze[$size - 1][$i] = '🧱';
        $maze[$i][0] = '🧱';
        $maze[$i][$size - 1] = '🧱';
    }
    // Add random walls inside the maze
    for ($i = 1; $i < $size - 1; $i++) {
        for ($j = 1; $j < $size - 1; $j++) {
            if (mt_rand(0, 100) < 30) {  // 30% chance of being a wall
                $maze[$i][$j] = '🧱';
            }
        }
    }
    // Ensure a path from the mouse to the cat
    $mouseRow = 1;
    $catRow = $size - 2;
    for ($col = 1; $col < $size - 1; $col++) {
        $maze[$mouseRow][$col] = '⬜';
        $maze[$catRow][$col] = '⬜';
    }
    for ($row = $mouseRow; $row <= $catRow; $row++) {
        $maze[$row][1] = '⬜';
    }
    return $maze;
}
// Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
// Handle maze type change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changeMazeType'])) {
    $_SESSION['mazeType'] = $_POST['mazeType'];
    // Reset the game
    unset($_SESSION['maze']);
    unset($_SESSION['mousePosition']);
    unset($_SESSION['catPosition']);
    unset($_SESSION['gameWon']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
// Check if we need to initialize a new game
if (!isset($_SESSION['maze']) || !isset($_SESSION['mousePosition']) || !isset($_SESSION['gameWon'])) {
    if (isset($_SESSION['mazeType']) && $_SESSION['mazeType'] === 'random') {
        $selectedMaze = randoMaze();
    } else {
        $mazes = include("maps.php");
        if (!is_array($mazes)) {
            die("Error: maps.php did not return an array of mazes.");
        }
        $selectedMaze = $mazes[array_rand($mazes)];
    }
    $mousePosition = findRandomMousePosition($selectedMaze);
    $catPosition = findRandomCatPosition($selectedMaze);
    // Fallback: If no valid positions found, find any non-wall position
    if ($mousePosition === null || $catPosition === null) {
        $allValidPositions = [];
        for ($i = 0; $i < count($selectedMaze); $i++) {
            for ($j = 0; $j < count($selectedMaze[$i]); $j++) {
                if ($selectedMaze[$i][$j] !== '🧱') {
                    $allValidPositions[] = ['row' => $i, 'col' => $j];
                }
            }
        }
        if (count($allValidPositions) >= 2) {
            shuffle($allValidPositions);
            $mousePosition = $allValidPositions[0];
            $catPosition = $allValidPositions[1];
        } else {
            die("Error: The maze doesn't have enough valid positions for both the cat and the mouse.");
        }
    }
    // Place the mouse in the maze
    $selectedMaze[$mousePosition['row']][$mousePosition['col']] = '🐭';
    $_SESSION['maze'] = $selectedMaze;
    $_SESSION['catPosition'] = $catPosition;
    $_SESSION['mousePosition'] = $mousePosition;
    $_SESSION['gameWon'] = false;
    // Initialize fog of war
    $_SESSION['fogOfWar'] = updateFogOfWar($selectedMaze, $catPosition);
} else {
    $selectedMaze = $_SESSION['maze'];
    $catPosition = $_SESSION['catPosition'];
    $mousePosition = $_SESSION['mousePosition'];
    $fogOfWar = $_SESSION['fogOfWar'];
}
// Ensure all necessary session variables are set
if (!isset($_SESSION['gameWon'])) {
    $_SESSION['gameWon'] = false;
}
// Ensure $selectedMaze is not null
if ($selectedMaze === null) {
    die("Error: Maze is not initialized properly.");
}
$message = '';
// Handle movement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['direction']) && !$_SESSION['gameWon']) {
    $mouseCaught = moveCat($selectedMaze, $catPosition, $_POST['direction'], $_SESSION['mousePosition']);
    $_SESSION['catPosition'] = $catPosition; // Update cat position in session
    $_SESSION['fogOfWar'] = updateFogOfWar($selectedMaze, $catPosition); // Update fog of war
    if ($mouseCaught) {
        $_SESSION['gameWon'] = true;
        $message = "GG you got it!";
    } else {
        // Move the mouse after the cat moves
        moveMouse($selectedMaze, $_SESSION['mousePosition'], $catPosition);
    }
    $_SESSION['maze'] = $selectedMaze; // Update maze in session
}
// Always use session variables for current state
$selectedMaze = $_SESSION['maze'];
$catPosition = $_SESSION['catPosition'];
$mousePosition = $_SESSION['mousePosition'];
$fogOfWar = $_SESSION['fogOfWar'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Kalnia+Glaze:wght@100..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <title>Bi Rint</title>
</head>

<body>
<div class="fog">
        <div class="fog-img"></div>
        <div class="fog-img fog-img-second"></div>
    </div>
    <header>
        <h1>
            Qui aime bien ChaRis bien
        </h1>
    </header>
    <main>
    <form action="_db/logout.php" method="post">
    <button type="submit">Logout</button>
</form>
        <div id="gameContainer">
            <form method="post" id="gameForm">
                <div class="key-row">
                    <label for="mazeType">Choose a maze:</label>
                    <select name="mazeType" id="mazeType" class="key">
                        <option value="manual" <?php echo isset($_SESSION['mazeType']) && $_SESSION['mazeType'] === 'manual' ? 'selected' : ''; ?>>Manual Maze</option>
                        <option value="random" <?php echo isset($_SESSION['mazeType']) && $_SESSION['mazeType'] === 'random' ? 'selected' : ''; ?>>Random Maze</option>
                    </select>
                    <button type="submit" name="changeMazeType" class="key">Apply</button>
                </div>
                <div id="virtual-keyboard">
                    <div class="key-row">
                        <button class="key" id="upButton" type="submit" name="direction" value="up" <?php echo isset($_SESSION['gameWon']) && $_SESSION['gameWon'] ? 'disabled' : ''; ?>>↑</button>
                    </div>
                    <div class="key-row">
                        <button class="key" id="leftButton" type="submit" name="direction" value="left" <?php echo isset($_SESSION['gameWon']) && $_SESSION['gameWon'] ? 'disabled' : ''; ?>>←</button>
                        <button class="key" id="downButton" type="submit" name="direction" value="down" <?php echo isset($_SESSION['gameWon']) && $_SESSION['gameWon'] ? 'disabled' : ''; ?>>↓</button>
                        <button class="key" id="rightButton" type="submit" name="direction" value="right" <?php echo isset($_SESSION['gameWon']) && $_SESSION['gameWon'] ? 'disabled' : ''; ?>>→</button>
                    </div>
                    <div class="key-row">
                        <button class="key" id="resetButton" type="submit" name="reset" value="true">Reset</button>
                    </div>
                </div>
            </form>
        </div>
        <?php
        displayMaze($selectedMaze, $fogOfWar, $catPosition);
        if (!empty($message)) {
            echo "<div class='message'>$message</div>";
        }
        ?>
    </main>
    <script>
        document.addEventListener('keydown', function(event) {
            if (<?php echo isset($_SESSION['gameWon']) && $_SESSION['gameWon'] ? 'true' : 'false'; ?>) {
                return; // If game is won, don't process keyboard inputs
            }

            switch (event.key) {
                case 'z':
                case 'Z':
                case 'ArrowUp':
                    document.getElementById('upButton').click();
                    break;
                case 's':
                case 'S':
                case 'ArrowDown':
                    document.getElementById('downButton').click();
                    break;
                case 'q':
                case 'Q':
                case 'ArrowLeft':
                    document.getElementById('leftButton').click();
                    break;
                case 'd':
                case 'D':
                case 'ArrowRight':
                    document.getElementById('rightButton').click();
                    break;
            }
        });
        // Prevent default arrow key scrolling
        window.addEventListener("keydown", function(e) {
            if (["ArrowUp", "ArrowDown", "ArrowLeft", "ArrowRight"].indexOf(e.code) > -1) {
                e.preventDefault();
            }
        }, false);
    </script>
</body>
</html>