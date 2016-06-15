<!DOCTYPE html>
<html>
<head>
    <title>npm.by parser</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
</head>
<body style="background-image: url(witch.png); background-repeat: no-repeat; background-attachment: fixed; background-position: right top;">
<div style="height: 20px;"> </div>
<div class="container">

<?php if ($message !== null): ?>
  <div class="alert alert-info" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <strong> <?php echo $message ?></strong> 
  </div>
<?php endif; ?>

<form action="" method="post">

    
    <div class="col-md-6 col-md-offset-1">
     <h3 style="text-align: center;">ONLINE БРОНИРОВАНИЕ МЕСТ</h3>
    </div>
    <div class="row"></div>
    <div class="col-md-3 col-md-offset-1">
        От куда
        <select class="form-control" name="npm[from]">
        <!-- 88  == 36  -->
            <?php foreach($stations as $item):?>
                <option value="<?php if ($item['id'] == 88) echo 36; else echo $item['id'] ?>">
                <?php echo $item['name'] ?></option>
            <?php endforeach;?>
        </select>
    </div>

    <div class="col-md-3">
        Куда
        <select class="form-control" name="npm[to]">
            <?php foreach($stations as $item):?>
                <option value="<?php echo $item['id'] ?>" > <?php echo $item['name'] ?></option>
            <?php endforeach;?>
        </select>
    </div>

    

    <div class="col-md-6 col-md-offset-1">
    <div style="height:20px"></div>
      <div class="form-group" style="width: 60%;">
        <label data-toggle="tooltip" data-placement="top" title="Для уведомления о наличии свободных мест">
        Электронная почта*</label>
        <input type="email" class="form-control"  placeholder="Email"  name="npm[email]" required>
      </div>
      <div style="height:10px"></div>
      <h4 data-toggle="tooltip" data-placement="top" title="Не обязательно к заполнению. Заполнить только для авто бронирования">Данные для авторизации на npm.by*</h4>
      <p style="color: #aaa; font-size: 0.7em;">Пароль и номер телефона не сохраняется в базе</p>
      <div style="display: flex;">
          
          <div class="form-group" style="padding: 10px;">
            <label>Пароль</label>
            <input type="password" class="form-control" name="npm[password]" placeholder="password">
          </div>

          <div class="form-group" style="padding:10px;">
            <label>Номер телефона (+375 xxxxxxxxx)</label>
            <input type="text" class="form-control" name="npm[phone]" placeholder="Пример заполнения: 331234567">
          </div>
      </div>

      <div class="form-group" style="width: 60%;">
        <label>Дата в формате dd-mm-YYYY</label>
        <input type="text" class="form-control"  placeholder="01-01-2016"  name="npm[date]" required>
      </div>

      <div class="form-group" style="width: 60%;">
        <label>Время отправления(час) в порядке приоритета</label>
        <textarea class="form-control" rows="3" required placeholder="Например 18, 17, 21, 12, 09" name="npm[time]"></textarea>
      </div>
      <input type="hidden" name="npm[token]" value="<?php echo $token ?>">

      <button type="submit" class="btn btn-default">Создать task</button>
    </div>
</form>

</div>

<script type="text/javascript">
$(function () {
  $('[data-toggle="tooltip"]').tooltip()
})
</script>
</body>
</html>