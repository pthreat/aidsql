<html>

	<head>
		<title>form test</title>
	</head>

	<body>


		<form method="POST" action="form.php">
			<input type="hidden" name="coco" value="5" />
		</form>

		<form method="POST" action="useless.php">
		</form>

		<form method="POST" action="action.php">

			<input type="text" name="name" />
			<input type="text" name="lastname" />

			<select name="combo">
				<option value="1">Option 1</option>
				<option value="2">Option 2</option>
				<option value="3">Option 3</option>
			</select>

			<div>
				<label for="gender"><input type="radio" name="gender" value="0"> Male</label>
				<label for="gender"><input type="radio" name="gender" value="1">Female</label>
			</div>

			<div>
				<input type="checkbox" name="prefered_jobs[]" value="1" />
				<input type="checkbox" name="prefered_jobs[]" value="2" />
				<input type="checkbox" name="prefered_jobs[]" value="3" />
				<input type="checkbox" name="prefered_jobs[]" value="4" />
				<input type="checkbox" name="prefered_jobs[]" value="5" />
				<input type="checkbox" name="prefered_jobs[]" value="6" />
				<input type="checkbox" name="prefered_jobs[]" value="7" />
				<input type="checkbox" name="prefered_jobs[]" value="8" />
			</div>

		</form>


		<form method="GET" name="pija" enctype="multipart/form-data">
			<input type="file" name="bleh" />
		</form>

	</body>

</html>
