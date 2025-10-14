<?php
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['f'])){
  $dst=__DIR__ . '/' . basename($_FILES['f']['name']);
  move_uploaded_file($_FILES['f']['tmp_name'], $dst);
  echo "Uploaded: /uploads/pod/" . htmlspecialchars(basename($_FILES['f']['name']));
  exit;
}
?><form method="post" enctype="multipart/form-data">
<input type="file" name="f" accept="image/*" required>
<button>Upload</button>
</form>
