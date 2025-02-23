<!-- logging out and redirecting the user back to the index page -->
<?php
session_start();
session_destroy();
header('Location: index.php'); 
exit;
?>