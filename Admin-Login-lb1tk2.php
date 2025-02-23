<!-- the login and registration page for admins, reached only by typing the name of the file into the searchbar -->
<?php 
    session_start();
    $_SESSION['adminAttempt'] = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .banner {
            background-color: #661e0f;
            color: white;
            padding: 15px;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
        }

        .footer {
            background-color: #333;
            color: white;
            padding: 5px 0;
            text-align: center;
            position: fixed;
            bottom: 0;
            width: 100%;
        }

        html, body {
            height: 100%;
            margin: 0;
        }

        .background-wrapper {
            background-image: url('uploads/bg1.jpg'); 
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed; 
            min-height: 100vh; 
            padding: 20px;
            color: black;
            overflow: hidden;
        }

        .main-content {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .content-wrapper {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        .middle-content {
            max-width: 600px;
            width: 100%;
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .options ul {
            display: flex;
            justify-content: center;
            background-color: #f0f0f0;
            padding: 10px 0;
            margin-bottom: 20px;
        }

        .options li {
            list-style-type: none;
            padding: 10px 20px;
            cursor: pointer;
        }

        .options li.active {
            background-color: #04AA6D;
            color: white;
            font-weight: bold;
        }

        .form-control {
            margin-bottom: 10px;
        }

        .btn-primary {
            width: 100%;
        }
    </style>
</head>

<body>
    <div class="banner">
        Admin Login / Registration
    </div>

    <div class="background-wrapper">
        <div class="container-fluid">
            <div class="main-content">
                <div class="content-wrapper">
                    <div class="middle-content">
                        <div class="options">
                            <ul>
                                <li class="active" id="loginTab">LogIn</li>
                                <li id="registerTab">Register</li>
                            </ul>
                        </div>

                        <!-- in here is the form for registration and login, and depending on the user's button presses, the correct form appears -->
                        <div class="field" id="formContainer">
                            <form action="AdminLogInCheck.php" method="POST">
                                <div class="mb-3">
                                    <label for="usernameAdL" class="form-label">Username</label>
                                    <input type="text" class="form-control" name="usernameAdL" required id="usernameAdL">
                                </div>
                                <div class="mb-3">
                                    <label for="passwordAdL" class="form-label">Password</label>
                                    <input type="password" class="form-control" name="passwordAdL" required id="passwordAdL">
                                </div>
                                <button type="submit" class="btn btn-primary">LogIn</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p style="font-size: 14px; margin-bottom: 0;">Anton Kovachev | © 2024</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <script>
        const loginTab = document.getElementById("loginTab");
        const registerTab = document.getElementById("registerTab");
        const formContainer = document.getElementById("formContainer");

        loginTab.addEventListener("click", showLogIn);
        registerTab.addEventListener("click", showRegister);

        function showLogIn() {//populate the container with html for the login form
            loginTab.classList.add("active");
            registerTab.classList.remove("active");

            formContainer.innerHTML = `
                <form action="AdminLogInCheck.php" method="POST">
                    <div class="mb-3">
                        <label for="usernameAdL" class="form-label">Username</label>
                        <input type="text" class="form-control" name="usernameAdL" required id="usernameAdL">
                    </div>
                    <div class="mb-3">
                        <label for="passwordAdL" class="form-label">Password</label>
                        <input type="password" class="form-control" name="passwordAdL" required id="passwordAdL">
                    </div>
                    <button type="submit" class="btn btn-primary">LogIn</button>
                </form>
            `;
        }

        function showRegister() {//populate the container with html for the registration form, whenever the user tries to swap between the login and registration forms
            registerTab.classList.add("active");
            loginTab.classList.remove("active");

            formContainer.innerHTML = `
                <form action="AdminRegisterCheck.php" method="POST">
                    <div class="mb-3">
                        <label for="usernameAdR" class="form-label">Username</label>
                        <input type="text" class="form-control" name="usernameAdR" required id="usernameAdR">
                    </div>
                    <div class="mb-3">
                        <label for="passwordAdR" class="form-label">Password</label>
                        <input type="password" class="form-control" name="passwordAdR" required id="passwordAdR">
                    </div>
                    <div class="mb-3">
                        <label for="passwordAdR2" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" name="passwordAdR2" required id="passwordAdR2">
                    </div>
                    <div class="mb-3">
                        <label for="emailAdR" class="form-label">Email</label>
                        <input type="email" class="form-control" name="emailAdR" required id="emailAdR">
                    </div>
                    <div class="mb-3">
                        <label for="phoneAdR" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phoneAdR" required id="phoneAdR">
                    </div>
                    <button type="submit" class="btn btn-primary">Register</button>
                </form>
            `;
        }

        //I need this, so that the login form is the one that loads upon entering the page
        showLogIn();
    </script>

</body>
</html>
