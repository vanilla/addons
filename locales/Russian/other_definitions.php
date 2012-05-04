<?php if (!defined('APPLICATION')) exit();
/**
* Locale: Russian (ru-RU)
* Description: A full translation to Russian including all default plugins and admin as of 2.0.18.2.
* Author: Petropoplsky Alexander <petropolsky@gmail.com> http://petropolsky.pp.ua
* Last changed: 2012-02-01
* License: Creative Commons Attribution-ShareAlike 3.0 (CC BY-SA)
*/

if (!function_exists('Plural')) {
	function Plural($Number, $Singular, $Plural)
	{
		$russian = array (
			'role' =>           array ('группа', 'группы', 'групп'),

			'%s message' =>		array ('%s сообщение', '%s сообщения', '%s сообщений'),
			'%s discussion' =>	array ('%s тема', '%s темы', '%s тем'),
			'%s comment' =>		array ('%s сообщение', '%s сообщения', '%s сообщений'),
			'%s New' =>			array ('%s Новое', '%s Новых', '%s Новых'),
			'%s new' =>			array ('%s новое', '%s новых', '%s новых'),
			
			'%s second' =>		array ('%s секунда', '%s секунды', '%s секунд'),
			'%s minute' =>		array ('%s минута', '%s минуты', '%s минут'),
			'%s hour' =>		array ('%s час', '%s часа', '%s часов'),
			'%s day' =>			array ('%s день', '%s дня', '%s дней'),
			'%s week' =>		array ('%s неделя', '%s недели', '%s недель'),
			'%s month' =>		array ('%s месяц', '%s месяца', '%s месяцев'),
			'%s year' =>		array ('%s год', '%s года', '%s лет'),

			'item' =>			array ('элемент', 'элемента', 'элементов'),
			'Comment' =>		array ('сообщение', 'сообщения', 'сообщений'),
			'other'	=>			array ('другой', 'другого', 'других'),
			'you'	=>			array ('вы', 'вас', 'вам'),
			'You'	=>			array ('Вы', 'Вас', 'Вам')
		);
   
		$num = str_replace(',', '', $Number ); 		// Remove commas
		$s = 2;
		if ( ! ( $num == 0 || ( $num > 4 && $num < 21 ) ) )
		{
			if		($num > 10) $n = $num % 10;
			else	$n = $num;
  
			if		($n == 1)	$s = 0;
			else	if ($n > 1 && $n < 5)	$s = 1;
		}		
		if ( isset ( $russian[$Singular][$s] ) )
			return sprintf ( $russian[$Singular][$s], $Number );
		else
			return sprintf(T($num == 1 ? $Singular : $Plural), $Number);
   }
}

$Definition['Locale'] = 'ru-RU';
$Definition['_Locale'] = 'Язык';

$Definition['(YYYY-mm-dd)'] = '(YYYY-mm-dd)';

$Definition['Date.DefaultDayFormat'] = '%A %e <font style="text-transform:lowercase;">%b</font> в %H:%M';
$Definition['Date.DefaultFormat'] = '%h %i, %Y, %H:%M';
$Definition['Date.DefaultTimeFormat'] = 'сегодня в %H:%M';
$Definition['Date.DefaultYearFormat'] = '%e <font style="text-transform:lowercase;">%b</font> %Y';
$Definition['Date.DefaultDateTimeFormat'] = '%B %e, %Y %l:%M%p';

$Definition['Date.WeekdayNames'] = array("Воскресенье","Понедельник","Вторник","Среда","Четверг","Пятница","Суббота"); 
$Definition['Date.WeekdayShortNames'] = array("Вс","Пон","Вт","Ср","Чт","Пт","Сб");
$Definition['Date.MonthNames'] = array("Январь","Февраль","Март","Апрель","Май","Июнь","Июль","Август","Сентябрь","Октябрь","Ноябрь","Декабрь");
$Definition['Date.MonthShortNames'] = array("Янв","Фев","Мар","Апр","Май","Июн","Июл","Авг","Сен","Окт","Ноя","Дек");

$Definition['Gender'] = 'Кто Вы?';
$Definition['Female'] = 'Девушка';
$Definition['Male'] = 'Мужчина';

$Definition['%1$s on %2$s'] = '%1$s на %2$s';
$Definition['%1$s Version %2$s'] = '%1$s Версия %2$s';
$Definition['%s user(s) found.'] = '%s пользователь(ей) найден(о).';

$Definition['[%1$s] Membership Approved'] = '[%1$s] Пользователей подтверждено';
$Definition['[%s] Welcome Aboard!'] = '[%s] Добро пожаловать!';
$Definition['Thank You!'] = 'Спасибо!';

$Definition['<General Error>'] = '<Общая ошибка>';
$Definition['<strong>%1$s</strong> (%2$s) %3$s'] = '<strong>%1$s</strong> (%2$s) %3$s';
$Definition['<strong>Heads Up!</strong> Moving discussions into a replacement category can result in discussions vanishing (or appearing) if the replacement category has different permissions than the category being deleted.'] = '<strong>Внимание!</strong> Перемещение темы в другую категорию может привести к её исчезновению, если заменяемая категория имеет другие права';
$Definition['<strong>The basic registration form requires</strong> that new users copy text from a "Captcha" image to keep spammers out of the site. You need an account at <a href="http://recaptcha.net/">recaptcha.net</a>. Signing up is FREE and easy. Once you have signed up, come back here and enter the following settings:'] = '<strong>Страница регистрации по умолчанию</strong> требует пользователей ввести <a href="#">КАПТЧУ</a>. Она защитит форум от спамеров. Вам необходимо зарегистрироваться на <a href="http://recaptcha.net/">recaptcha.net</a> для получения уникальных ключей. Регистрация не отнимет у вас много времени. После того как зарегистрируетесь, и получите ключи, Вам необходимо будет ввести их в форму ниже:';
$Definition['1 month after being sent'] = 'Месяц после отправки';
$Definition['1 week after being sent'] = 'Неделю после отправки';
$Definition['2 weeks after being sent'] = 'Две недели после отправки';

$Definition['%1$s created an account for %4$s.'] = '%4$s создал аккаунт для %1$s.';
$Definition['%s and you'] = '%s и вы';
$Definition['%s comment'] = 'Сообщений: %s';
$Definition['%s comments'] = 'Сообщений: %s';
$Definition['%s Connect'] = '%s Соеденен';
$Definition['%s discussion'] = '%s тем';
$Definition['%s discussions'] = '%s тем';
$Definition['%s message'] = '%s сообщение';
$Definition['%s messages'] = '%s сообщения';
$Definition['%s New'] = '%s Новое';
$Definition['%s new'] = '%s новых';
$Definition['%s New Plural'] = 'Новые сообщения: %s';

$Definition['1 message'] = 'Одно сообщение';

$Definition['%1$s mentioned %3$s in a %8$s.'] = '%1$s упомянул меня в %8$s';
$Definition['%1$s was added to the %2$s %3$s.'] = '%1$s был добавлен в группу "%2$s"';
$Definition['%1$s was removed from the %2$s %3$s and added to the %4$s %5$s.'] = '%1$s был перенесен из группы "%2$s" в группу "%4$s"';
$Definition['%1$s was removed from the %2$s %3$s.'] = '%1$s был был удален из группы "%2$s"';
$Definition['%s changed %s\'s permissions.'] = '%s изменил настройки доступа';
$Definition['%s mentioned %s in a %s.'] = '%s упомянул вас в отзыве';

$Definition['%s tags in the system'] = '%s тэгов в системе';
$Definition['%s was removed from the %s and added to the %s'] = '%s был удален из %s и добавлен в %s';
$Definition['&larr; All Discussions'] = '&larr; Все темы';
$Definition['&lt;Embed&gt; Vanilla'] = '&lt;Embed&gt; Vanilla';
$Definition['A url-friendly version of the category name for better SEO.'] = 'Url-версия названия категории для улучшения SEO.';

$Definition['About'] = 'Подробная информация';
$Definition['Accepted'] = 'Принят(а)';
$Definition['Action'] = 'Действия';
$Definition['Activate'] = 'Активированный';
$Definition['Active Users'] = 'Активные пользователи';
$Definition['Activity.AboutUpdate.FullHeadline'] = '%1$s обновили профиль %6$s.';
$Definition['Activity.AboutUpdate.ProfileHeadline'] = '%1$s обновили профиль %6$s.';
$Definition['Activity.ActivityComment.FullHeadline'] = '%1$s оставили комментарий в %4$s %8$s.';
$Definition['Activity.ActivityComment.ProfileHeadline'] = '%1$s';
$Definition['Activity.AddedToConversation.FullHeadline'] = '%1$s добавили себя в %8$s.';
$Definition['Activity.AddedToConversation.ProfileHeadline'] = '%1$s добавили себя в %8$s.';
$Definition['Activity.BookmarkComment.FullHeadline'] = '%1$s комментирует вашу %8$s.';
$Definition['Activity.BookmarkComment.ProfileHeadline'] = '%1$s комментирует вашу %8$s.';
$Definition['Activity.Comment'] = 'Ваш отзыв';
$Definition['Activity.CommentMention.FullHeadline'] = '%1$s упомянули %3$s в %8$s.';
$Definition['Activity.CommentMention.ProfileHeadline'] = '%1$s упомянули %3$s в %8$s.';
$Definition['Activity.ConversationMessage.FullHeadline'] = '%1$s отправил %8$s для %3$s';
$Definition['Activity.ConversationMessage.ProfileHeadline'] = '%1$s отправил %8$s для %3$s';
$Definition['Activity.Delete'] = '×';//Удалить
$Definition['Draft.Delete'] = '×';
$Definition['Activity.DiscussionComment.FullHeadline'] = '%1$s ответил(а) в вашей %8$s.';
$Definition['Activity.DiscussionComment.ProfileHeadline'] = '%1$s ответил(а) в вашей %8$s.';
$Definition['Activity.DiscussionMention.FullHeadline'] = '%1$s вспомнил(ла) %3$s в %8$s.';
$Definition['Activity.DiscussionMention.ProfileHeadline'] = '%1$s вспомнил(ла) %3$s в %8$s.';
$Definition['Activity.Import.FullHeadline'] = '%1$s импортировал(ла) данные.';
$Definition['Activity.Import.ProfileHeadline'] = '%1$s импортировал(ла) данные.';
$Definition['Activity.Join.FullHeadline'] = 'теперь %1$s участник(ца) форума.';
$Definition['Activity.Join.ProfileHeadline'] = 'теперь %1$s участник(ца) форума';
$Definition['Activity.JoinApproved.FullHeadline'] = '%1$s подтвердил(ла) заявку от %4$s.';
$Definition['Activity.JoinApproved.ProfileHeadline'] = '%1$s подтвердил(ла) заявку от %4$s.';
$Definition['Activity.JoinCreated.FullHeadline'] = '%1$s создал(а) аккаунт для %3$s.';
$Definition['Activity.JoinCreated.ProfileHeadline'] = '%3$s создал(а) аккаунт для %1$s.';
$Definition['Activity.JoinInvite.FullHeadline'] = '%1$s одобрил(а) %4$s инвайтов';
$Definition['Activity.JoinInvite.ProfileHeadline'] = '%1$s одобрил(а) %4$s инвайтов';
$Definition['Activity.NewDiscussion.FullHeadline'] = '%1$s создал(а) новую %8$s.';
$Definition['Activity.NewDiscussion.ProfileHeadline'] = '%1$s создал(а) новую %8$s.';
$Definition['Activity.PictureChange.FullHeadline'] = '%1$s обновил(а) фотографию на странице:';
$Definition['Activity.PictureChange.ProfileHeadline'] = '%1$s обновил(а) фотографию на странице:';
$Definition['Activity.RoleChange.FullHeadline'] = '%1$s изменил разрешения для %4$s';
$Definition['Activity.RoleChange.ProfileHeadline'] = '%1$s изменил разрешения для %4$s';
$Definition['Activity.SignIn.FullHeadline'] = '%1$s онлайн.';
$Definition['Activity.SignIn.ProfileHeadline'] = '%1$s онлайн.';
$Definition['Activity.WallComment.FullHeadline'] = '%1$s написал в %4$s %5$s.';
$Definition['Activity.WallComment.ProfileHeadline'] = '%1$s написал(ла):';
$Definition['Activity'] = 'Актив';
$Definition['Add'] = 'Добавить';
$Definition['Add a Comment'] = 'Оставить отзыв';
$Definition['Add Category'] = 'Добавить категорию';
$Definition['Add Comment'] = 'Отправить';
$Definition['Add Info &amp; Create Account'] = 'Добавить информацию &amp; Создать аккаунт';
$Definition['Add Item'] = 'Добавить запись';
$Definition['Add Message'] = 'Добавить сообщение';
$Definition['Add People to this Conversation'] = 'Добавить людей к разговору? <i>(Введите имя пользователя)</i>';
$Definition['Add Role'] = 'Добавить новую группу';
$Definition['Add Route'] = 'Добавить перенаправление';
$Definition['Add User'] = 'Добавить пользователя';
$Definition['Added By'] = 'Добавил';
$Definition['Adding & Editing Categories'] = 'Добавление и редактирование категорий';
$Definition['Addons'] = 'Дополнения';
$Definition['Administrator'] = 'Администратор';
$Definition['Advanced Forum Settings'] = 'Продвинутые установки форума';
$Definition['Advanced'] = 'Настройки';
$Definition['All %1$s'] = 'Все %1$s';
$Definition['All Categories'] = 'Все категории';
$Definition['All Conversations'] = 'Все разговоры';
$Definition['All discussions in this category will be permanently deleted.'] = 'Все темы в этой категории будут удалены.';
$Definition['All Discussions'] = 'Все темы';
$Definition['All Forum Pages'] = 'На всех страницах форума';
$Definition['All'] = 'Все';
$Definition['All of the user content will be replaced with a message stating the user has been deleted.'] = 'Все сообщения пользователя будут заменены сообщением о том, что этот пользователь был удален.';
$Definition['Allow other members to see your email?'] = 'Показывать ваш email<br>другим пользователям?';
$Definition['Allow users to dismiss this message'] = 'Разрешить пользователям скрывать это сообщение';
$Definition['Allow'] = 'Одобрить';
$Definition['Announce'] = 'Анонс'; // Всегда сверху
$Definition['Announcement'] = 'Объявление';
$Definition['Appearance'] = 'Внешний вид';
$Definition['Applicant Role'] = 'Выберите группу для новых кандидатов';
$Definition['Applicant'] = 'Кандидат';
$Definition['Applicants'] = 'Ожидают одобрения';
$Definition['Application ID'] = 'ID Приложения';
$Definition['Application Secret'] = 'Скрытый пароль';
$Definition['Application'] = 'Приложение';
$Definition['ApplicationHelp'] = 'С помощью приложений вы можете изменить базовую функциональность форума.<br />Сразу после того, как вы загрузите приложение в эту %s категорию, вы сможете активировать его в этой панели.';
$Definition['Applications'] = 'Приложения';
$Definition['Apply for Membership'] = 'Зарегистрироваться';
$Definition['Apply'] = 'Включить';
$Definition['Approval'] = 'После проверки';
$Definition['Approve'] = 'Допуск';
$Definition['Archive Discussions'] = 'Архив сообщений';
$Definition['Are you sure you want to delete %s items forever?'] = 'Вы уверены, что хотите навсегда удалить %s отзыва(ов)?';
$Definition['Are you sure you want to delete 1 item forever?'] = 'Вы уверены, что хотите навсегда удалить 1 отзыв?';
$Definition['Are you sure you want to do that?'] = 'Вы уверены, что хотите сделать это?';
$Definition['at'] = 'at';
$Definition['Attachments'] = 'Прикреплённые файлы';
$Definition['Authentication'] = 'Аутентификация';
$Definition['Authors can always edit their posts'] = 'Автор всегда может редактировать свой пост';
$Definition['Authors can edit for 1 day after posting'] = 'Автор не сможет редактировать свой пост спустя сутки после публикации';
$Definition['Authors can edit for 1 month after posting'] = 'Автор не сможет редактировать свой пост через месяц после публикации';
$Definition['Authors can edit for 1 week after posting'] = 'Автор не сможет редактировать свой пост через неделю после публикации';
$Definition['Authors can edit for 15 minutes after posting'] = 'Автор не сможет редактировать свой пост через 15 минут после публикации';
$Definition['Authors can edit for 30 minutes after posting'] = 'Автор не сможет редактировать свой пост через 30 минут после публикации';
$Definition['Authors can edit for 5 minutes after posting'] = 'Автор не сможет редактировать свой пост через 5 минут после публикации';
$Definition['Authors cannot edit their posts'] = 'Автору вообще нельзя редактировать опубликованные посты';

$Definition['Back to all users'] = 'Назад ко всем пользователям';
$Definition['Back to Discussions'] = 'Вернуться к просмотру тем';
$Definition['Ban Item'] = 'Запись';
$Definition['Ban List'] = 'Список забаненых';
$Definition['Ban Type'] = 'Тип бана';
$Definition['BanType'] = 'Тип бана';
$Definition['Ban Value'] = 'Значение';
$Definition['Banned'] = 'Забанен';
$Definition['Banner Logo'] = 'Логотип сайта';
$Definition['Banner Title'] = 'Название сайта';
$Definition['Banner'] = 'Название и лого';
$Definition['Basic'] = 'По умолчанию';
$Definition['Blocked'] = 'Заблокированно';
$Definition['Blogger Gadget'] = 'Гаджеты блоггера';
$Definition['Body'] = 'текст сообщения';
$Definition['Bookmark'] = 'Добавить в закладки';
$Definition['bookmarked discussion'] = 'отмеченные темы';
$Definition['Bookmarked Discussions'] = 'Ваши закладки';
$Definition['Browse for a new banner logo if you would like to change it:'] = 'Просто загрузите новый логотип, если вы хотите сменить текущий:';
$Definition['By %s'] = 'От %s';
$Definition['By clicking the button below, you will be deleting the user account for %s forever.'] = 'Нажав на кнопку ниже, вы удалите учетную запись %s навсегда.';
$Definition['By uploading a file you certify that you have the right to distribute this picture and that it does not violate the Terms of Service.'] = 'Загружая изображение, вы автоматически подтверждаете, что имеете право распространять загружаемое изображение и не нарушаете <strong>авторские права правообладателя</strong>.';
$Definition['By'] = 'От';

$Definition['Cancel'] = 'Отмена';
$Definition['Capture definitions throughout the site. You must visit the pages in the site in order for the definitions to be captured. The captured definitions will be put in the <code>captured.php</code> and <code>captured_admin.php</code>.'] = 'Перехват настроек сайта. Вы должны последовательно посетить все страницы сайта, чтобы настройки были сохранены. Все настройки будут находиться в файлах <code>captured.php</code> и <code>captured_admin.php</code>.';
$Definition['Capture locale pack changes.'] = 'Перехвачены изменения в одном из ваших пакетов локализации и разработчиком локали. Они будут записаны в файл <code>changes.php</code>.';
$Definition['Categories &amp; Discussions'] = 'Категории &amp; Темы';
$Definition['Categories are used to help organize discussions.'] = 'Категории используются для того, чтобы организовать дисскуссии.<br /> Вы можете делать многоуровневое меню сайта, с помощью функции <i>drag and drop</i> в списке категорий. ';
$Definition['Categories are used to organize discussions.'] = '<strong>Категории</strong> позволяют организовать форум.';
$Definition['Categories'] = 'Категории';
$Definition['Category Page Layout'] = 'Шаблон страницы категорий';
$Definition['Category to Use'] = 'Используемая категория';
$Definition['Category Url:'] = 'URL адрес:';
$Definition['Category'] = 'Категория';
$Definition['Change My Password'] = 'Изменить мой пароль';
$Definition['Change My Picture'] = 'Изменить мое изображение';
$Definition['Change Password'] = 'Изменение пароля';
$Definition['Change Picture'] = 'Изменение изображения';
$Definition['Change the look of All Categories'] = 'Вы можете изменить внешний вид <b>Всех категорий</b> <a href="%s">здесь</a>.';
$Definition['Change the way that new users register with the site.'] = 'На этой странице вы можете изменить настройки регистрации новых пользователей.';
$Definition['Changing the Discussions Menu Link'] = 'Обсуждение настройки контекстного меню';
$Definition['Check all permissions that apply for each role'] = 'Проверьте права доступа, которые применяются в каждой группе.';
$Definition['Check all permissions that apply to this role:'] = 'Проверьте права доступа, которые относятся к этой группе:';
$Definition['Check all roles that apply to this user:'] = 'Проверьте все группы, в которых может состоять этот пользователь:';
$Definition['Choose a locale pack'] = 'Выберите локализацию';
$Definition['Choose a name to identify yourself on the site.'] = 'Выберите имя для идентификации себя на сайте';
$Definition['Choose and configure your forum\'s authentication scheme.'] = 'Выберите и настройте схему авторизации на форуме';
$Definition['Choose how to handle all of the content associated with the user account for %s (comments, messages, etc).'] = 'Как обрабатывать содержимое для %s ? (отзывы, уведомления, и т.д.).';
$Definition['Choose who can send out invitations to new members:'] = 'Укажите, кто может приглашать новых пользователей:';
$Definition['Clear'] = 'Очистить';
$Definition['Click here to apply it.'] = 'Кликните здесь для подтверждения';
$Definition['Close'] = 'Закрыть'; //Закрыть
$Definition['Closed'] = 'Закрыто';
$Definition['Comment by %s'] = 'Отзыв %s';
$Definition['comment(s)'] = 'отзыв(ов)';
$Definition['Comment'] = 'Отправить';
$Definition['comment'] = 'отзывы';
$Definition['Commenting not allowed.'] = 'Комментирование запрещено.';
$Definition['Comments per Page'] = 'Отзывов на странице';
$Definition['Comments'] = 'Отзывы';
$Definition['Configure an Authenticator'] = 'Настройки аутентификации';
$Definition['Configure how nested categories are displayed to users.'] = 'Настройте количество вложенных категорий, видимых пользователям';
$Definition['Configuring Vanilla\'s Homepage'] = 'Настройте домашнюю страницу сайта';
$Definition['Confirm Email'] = 'Подтвердите email';
$Definition['Confirm'] = 'Подтверждение';
$Definition['Connect'] = 'Соединение';
$Definition['Consumer Key'] = 'Ключ потребителя';
$Definition['Consumer Secret'] = 'Секретный ключ потребителя';
$Definition['Content Flagging'] = 'Пометить содержимое';
$Definition['Content'] = 'Содержание';
$Definition['Conversation Messages'] = 'Сведения';
$Definition['conversation'] = 'диалоги';
$Definition['Conversations'] = 'Диалоги';
$Definition['Copy'] = 'Копировать';
$Definition['Copy locale pack.'] = 'Копирование настроек из пакета локализации в раздел разработчика. Настройки будут сохранены в файле <code>copied.php</code>.';
$Definition['Create Discussions'] = 'Создать обсуждение';
$Definition['Create One.'] = 'Зарегистрируйтесь!';
$Definition['Current Authenticator'] = 'Текущий плагин аутентификации';
$Definition['Current Theme'] = 'Используемая тема';

$Definition['Dashboard Summaries'] = '<b>Панель управления</b>. Основная информация';
$Definition['Dashboard'] = 'Панель управления';
$Definition['Database Structure Upgrades'] = 'Обновление структуры базы данных';
$Definition['Date'] = 'Дата';
$Definition['Decline'] = 'Отклонить';
$Definition['Default %s Permissions'] = 'Стандартные %s разрешения';
$Definition['Default Category Permissions'] = 'Разрешения на категории по умолчанию';
$Definition['Default Locale'] = 'Язык по-умолчанию';
$Definition['Default Roles'] = 'Группа для новых пользователей по умолчанию';
$Definition['Delete Account'] = 'Удалить аккаунт';
$Definition['Delete Category'] = 'Удалить категорию';
$Definition['Delete Conversation'] = 'Удалить все сообщения';
$Definition['Delete Discussion'] = 'Удалить тему';
$Definition['Delete Forever'] = 'Удалить навсегда';
$Definition['Delete the user and all of the user\'s content. This will cause discussions to be disjointed, appearing as though people are responding to content that is not there. This is a great option for removing spammer content.'] = 'Удалить пользователя и содержимое его профиля. Это может вызвать некоторую путаницу, т.к. пользователи ответившие ранее на сообщения этого пользователя - не увидят их. Хорошо подходит для удаления спама.';
$Definition['Delete the user, but just replace all of the user\'s content with a message stating the user has been deleted. This will give other users a visual cue that there is missing information so they better understand how a discussion might have flowed before the deletion.'] = 'Удалить пользователя и заменить все его сообщения сообщением о том, что пользователь был удален.';
$Definition['Delete User Content'] = 'Удалить сообщения пользователя';
$Definition['Delete User Forever'] = 'Удалить пользователя навсегда';
$Definition['Delete User: %s'] = 'Удалить пользователя: %s';
$Definition['Delete User'] = 'Удалить пользователя';
$Definition['Delete'] = 'Удалить';
$Definition['Deliting is a good way to keep your forum clean. However, when you delete forever then those operations are removed from this list and cannot be undone.'] ='Удаляя лишние данные, вы поддерживаете чистоту форума. Но не забывайте, что эти данные восстановлению не подлежат.';
$Definition['Deleting category...'] = 'Удаление категории...';
$Definition['Description'] = 'Описание';
$Definition['Did You Know?'] = 'А Вы знаете?';
$Definition['Disable Content Flagging'] = 'Выключить жалобы на контент';
$Definition['Disable Google Sign In'] = 'Выключить авторизацию с помощью Google';
$Definition['Disable OpenID'] = 'Выключить OpenID';
$Definition['Disable Tagging'] = 'Выключить теги';
$Definition['Disable'] = 'Выключить';
$Definition['Disabled %1$s'] = 'Неактивные %1$s';
$Definition['Disabled'] = 'Выключено';
$Definition['Disabled <span>0</span>'] = 'Выключить <span>0</span>';
$Definition['Disabled <span>10</span>'] = 'Выключить <span>10</span>';
$Definition['Disabled <span>11</span>'] = 'Выключить <span>11</span>';
$Definition['Disabled <span>12</span>'] = 'Выключить <span>12</span>';
$Definition['Disabled <span>9</span>'] = 'Выключить <span>9</span>';
$Definition['Discussion & Comment Editing'] = 'Редактирование Тем и Отзывов';
$Definition['Discussion Title'] = 'Заголовок темы';
$Definition['discussion(s)'] = 'Тем';
$Definition['discussion'] = 'тему';
$Definition['Discussion'] = 'Темы';
$Definition['Discussions per Page'] = 'Тем на странице';
$Definition['Discussions'] = 'Все темы';
$Definition['Dismiss'] = 'Отменить';
$Definition['Display root categories as headings.'] = 'Показывать главные категории как заголовки.';
$Definition['Do not display the categories in the side panel.'] = 'Не показывать список категорий в сайдбаре.';
$Definition['Don\'t have an account? %s'] = 'Нет аккаунта? %s';
$Definition['Don\'t Refresh'] = 'Не обновлять';
$Definition['Don\'t use Categories'] = 'Не использовать категории';
$Definition['dot'] = 'dot';
$Definition['Downloads'] = 'Скачать';
$Definition['Draft saved at %s'] = 'Черновик сохранен %s';
$Definition['Drag and drop the categories below to sort and nest them.'] = 'Схватите и перетащите категорию для сортировки в списке.';
$Definition['Drag around and resize the square below to define your thumbnail icon.'] = 'Выделите и измените размер вашего аватара.';

$Definition['Edit Account'] = 'Редактировать аккаунт';
$Definition['Edit Category'] = 'Редактировать категорию';
$Definition['Edit Discussion'] = 'Редактировать обсуждение';
$Definition['Edit Message'] = 'Редактировать сообщение';
$Definition['Edit My Account'] = 'Редактировать мой аккаунт';
$Definition['Edit My Thumbnail'] = 'Изменить мой аватар';
$Definition['Edit Preferences'] = 'Изменить настройки';
$Definition['Edit Role'] = 'Редактировать группы';
$Definition['Edit Route'] = 'Редактировать права';
$Definition['Edit Thumbnail'] = 'Редактирование аватара';
$Definition['Edit User'] = 'Редактировать пользователя';
$Definition['Edit/Delete Log'] = 'Лог редактирования';
$Definition['edit'] = 'редактировать';
$Definition['Edit'] = 'Редактировать';
$Definition['EditContentTimeout.Notes'] = 'Примечание: Если в группе пользователя стоят настройки, разрешающие редактирование в любое время - им будет отдано предпочтение';
$Definition['Email Notifications'] = 'Уведомления по Email';
$Definition['Email sent from the application will be addressed from the following name and address'] = 'Использовать в качестве отправителя следующий адрес электронной почты:';
$Definition['Email Unavailable'] = 'Почта недоступна';
$Definition['Email visible to other users'] = 'Email виден другим пользователям';
$Definition['Email/Username'] = 'Почта или Логин';
$Definition['Email'] = 'Email';
$Definition['Enable Content Flagging'] = 'Включить содержания отчета';
$Definition['Enable Google Sign In'] = 'Включить авторизацию с помощью Google';
$Definition['Enable OpenID'] = 'Включить OpenID';
$Definition['Enable Tagging'] = 'Включить тэги';
$Definition['Enable this message'] = 'Включить это сообщение';
$Definition['Enable'] = 'Включить';
$Definition['Enabled %1$s'] = 'Доступные %1$s';
$Definition['Enabled'] = 'Включено';
$Definition['Enabling a Locale Pack'] = 'Включение локализации';
$Definition['Enter the email address of the person you would like to invite:'] = 'Введите адрес электронной почты человека, которого вы хотели бы пригласить:';
$Definition['Enter the url to the page you would like to use as your homepage:'] = 'Введите URL на страницу, которую вы бы хотели использовать в качестве главной страницы:';
$Definition['Enter your Email address or username'] = 'Ведите ваш Email или логин';
$Definition['Enter your Email address'] = 'Адрес вашей почты';
$Definition['Erase User Content'] = 'Удалить сообщения пользователя';
$Definition['ErrorBadInvitationCode'] = 'Ошибка: неверный код приглашения';
$Definition['ErrorCredentials'] = 'К сожалению, нет доступа к этой учетной записи';
$Definition['ErrorPermission'] = 'Простите, но у вас нет разрешения';
$Definition['ErrorPluginDisableRequired'] = 'Вы не можете отключить плагин {0}, так как плагин зависит от {1}.';
$Definition['ErrorPluginEnableRequired'] = 'Прежде чем включить этот плагин, необходимо включить плагин: {0}.';
$Definition['ErrorPluginVersionMatch'] = 'Активированный {0} плагин (версии {1}) не отвечает условиям, необходимым версии ({2}).';
$Definition['ErrorRecordNotFound'] = 'Запрошеная запись не найдена';
$Definition['ErrorTermsOfService'] = 'Вы должны согласиться с условиями использования.';
$Definition['Every 1 minute'] = 'Каждую минуту';
$Definition['Every 10 seconds'] = 'Каждые 10 сек';
$Definition['Every 30 seconds'] = 'Каждые 30 сек';
$Definition['Every 5 minutes'] = 'Каждые 5 мин';
$Definition['Every 5 seconds'] = 'Каждые 5 сек';
$Definition['Every'] ='Везде';
$Definition['Exclude archived discussions from the discussions list'] = 'Исключить архив дискуссий из списка обсуждений';
$Definition['Existing members send invitations to new members.'] = 'Зарегистрированные пользователи приглашают новых пользователей.';

$Definition['Facebook Connect allows users to sign in using their Facebook account.'] = 'Подключение через Facebook позволит использовать Facebook аккаунт. <b>Для того, чтобы плагин работал, вы должны быть зарегистрированы на Facebook.</b>';
$Definition['Facebook Settings'] = 'Настройки Facebook';
$Definition['First Visit'] = 'Первый визит';
$Definition['Flag this Discussion'] = 'Пожаловаться на тему';
$Definition['Flag this!'] = 'Пожаловаться!';
$Definition['Flag'] = 'Пожаловаться';
$Definition['Flagged Content'] = 'Отмеченый контент';
$Definition['Flagged Items'] = 'Отмеченые элементы';
$Definition['Flagging Settings'] = 'Содержание отчета - Настройки';
$Definition['Flood Control'] = 'Антиспам';
$Definition['Follows'] = 'Следят';
$Definition['Forgot?'] = 'Забыли?';
$Definition['For more help on localization check out the page <a href="%s">here</a>.'] = 'Чтобы получить помощь по локализациям, изучите<a href="%s"> эту</a> страницу.';
$Definition['Forum Settings'] = 'Настройки форума';
$Definition['Forum'] = 'Форум';

$Definition['Garden.Email.SupportAddress'] = 'Email поддержки';
$Definition['Garden.Email.SupportName'] = 'Название поддержки';
$Definition['Garden.Import.Description'] = 'Эта страница служит для импорта данных из другого форума, который был экспортирован с помощью виджета экспорта Vanilla. Дополнительную информацию вы можете получить в <a href="%s">документации</a>.';
$Definition['Garden.Import.InputInstructions'] = 'Email и пароль администратора для источника импорта.';
$Definition['Garden.Import.Overwrite.Description'] = 'Внимание! Все данные в этом форуме будут перезаписаны.';
$Definition['Garden.Registration.DefaultRoles'] = 'по-умолчанию';
$Definition['Garden.Title'] = 'Заголовок';
$Definition['Garden'] = 'Garden';
$Definition['GenderSuffix.First.f'] = '';
$Definition['GenderSuffix.First.m'] = '';
$Definition['GenderSuffix.Third.f'] = '';
$Definition['GenderSuffix.Third.m'] = '';
$Definition['General'] = 'Новости';
$Definition['General discussions'] = 'Основные темы';
$Definition['Generate Password'] = 'Генерировать пароль';
$Definition['Generate'] = 'Генерировать';
$Definition['Get More Applications'] = 'Новые приложения';
$Definition['Get more information on creating custom routes'] = 'Читайте более подробную информацию о перенаправлениях.';
$Definition['Get More Plugins'] = 'Новые плагины';
$Definition['Get More Themes'] = 'Новые темы';
$Definition['Go'] = '&rarr;';
$Definition['Google Sign In Settings'] = 'Настройки авторизации Google';
$Definition['Guest roles'] = 'Отметить все группы, которые относятся к гостям.';
$Definition['Guest'] = 'Гость';
$Definition['GuestModule.Message'] = 'Похоже, Вы новенький! Чтобы начать обсуждение, кликните на одну из кнопок ниже ;)';

$Definition['he'] = 'её';
$Definition['her'] = 'её';
$Definition['here'] = 'здесь';
$Definition['his'] = 'его';
$Definition['Heads Up! This is a special role that does not allow active sessions. For this reason, the permission options have been limited to "view" permissions.'] = 'Здесь недоступны активные соединения. По этой причине Вам отказано в доступе.';
$Definition['Hide for non members of the site'] ='Скрыть от гостей';
$Definition['Homepage'] = 'Главная страница';
$Definition['Howdy, Stranger!'] = 'Привет, незнакомец!';

$Definition['I agree to the <a id="TermsOfService" class="Popup" target="terms" href="%s">terms of service</a>'] = 'Соглас(на)ен с <a id="TermsOfService" class="Popup" target="terms" href="%s">правилами</a> форума';
$Definition['I agree to the <a id="TermsOfService" class="Popup" target="terms" href="/dashboard/home/termsofservice">terms of service</a>'] = 'Соглас(на)ен с <a id="TermsOfService" class="Popup" target="terms" href="/dashboard/home/termsofservice">правилами</a> форума';
$Definition['I remember now!'] = 'Ой, вспомнил(а) пароль!';
$Definition['Import'] = 'Импорт';
$Definition['Importing to Vanilla'] = 'Импортировать в Vanill\'u';
$Definition['In this Conversation'] = 'Пользователи';
$Definition['In this Discussion'] = 'В этом теме:';
$Definition['Inbox'] = 'Входящие';
$Definition['Information'] = 'Информация';
$Definition['Internal'] = 'Внутренний';
$Definition['Internaltionalization & Localization'] = 'Интернационализация и локализация';
$Definition['Invitation Code'] = 'Код инвайта';
$Definition['Invitation'] = 'По приглашению';
$Definition['Invitations can be sent from users\' profile pages.'] = 'Если включена регистрация по инвайтам, на страницах профилей пользователей<br>отображается дополнительная ссылка, называемая <a href="%s" class="Popup">Мои приглашения</a>';
$Definition['Invitations per month'] = 'Приглашений в месяц';
$Definition['Invitations will expire'] = 'Приглашение истекает';
$Definition['Invite'] = 'Инвайт';
$Definition['Invited by'] = 'Приглашён';
$Definition['InviteErrorPermission'] = 'Простите, но у вас нет прав.';
$Definition['It is a good idea to keep the maximum number of characters allowed in a comment down to a reasonable size.'] = 'Опция ниже поможет вам сократить максимальное количество символов в отзывах до разумного размера.';
$Definition['item'] = 'Вклад';
$Definition['Item'] = 'Запись';

$Definition['Joined'] = 'С нами с ';
$Definition['Just delete the user record, and keep all of the user\'s content.'] = 'Удалить пользователя, но оставить все его записи.';

$Definition['Keep me signed in'] = 'Запомнить меня!';
$Definition['Keep User Content'] = 'Оставить контент пользователя';
$Definition['Key Type'] = 'Тип ключа';
$Definition['Key Value'] = 'Значение ключа';

$Definition['Last Active'] = 'Был на сайте';
$Definition['Last Visit'] = 'Последний визит';
$Definition['Latest %1$s'] = 'Последний %1$s';
$Definition['Leave blank unless connecting to an exising account.'] = 'Оставьте пустым, если не хотите быть подключены к существующей учетной записи.';
$Definition['Locale Developer Settings %s.'] = 'Настройки локальной разработки %s.';
$Definition['Locale Developer'] = 'Локальная разработка';
$Definition['Locale info file settings.'] = '<p>Перед созданием архива заполните пожалуйста поля, расположенные ниже.</p><p>Нажмите <a href="%s">здесь</a> чтобы загрузить файл.</p>';
$Definition['Locale Key (Folder)'] = 'Ключ локализации (папка)';
$Definition['Locale Name'] = 'Название';
$Definition['Locales are in your %s folder.'] = 'Локализация позволяет поддерживать несколько языков на форуме.<br /><br /> Как только вы добавите локализацию в папку %s , она отобразиться в списке ниже и вы сможете её активировать. <br /><br />';
$Definition['Locales'] = 'Локализации';
$Definition['Location'] = 'Местоположение';
$Definition['Login with Facebook'] = 'Войти с Facebook';

$Definition['Make sure to use a forum theme that meshes well with the look and feel of the remote site.'] = 'Удостоверьтесь, что выбранная тема сочетается с другими элементами дизайна.';
$Definition['Make sure you click View Page'] = 'Кликните <a href="%s">по ссылке</a>, чтобы увидеть какие страницы категорий появятся после сохранения.';
$Definition['Make sure you select at least one item before continuing.'] = 'Удостоверьтесь, что вы выбрали хотя бы одну запись перед редактированием.';
$Definition['Manage Applicants'] = 'Управление неактивными';
$Definition['Manage Applications'] = 'Управление приложениями';
$Definition['Manage Categories'] = 'Управление категориями';
$Definition['Manage Messages'] = 'Управление сообщениями';
$Definition['Manage Plugins'] = 'Управление плагинами';
$Definition['Manage Roles & Permissions'] = 'Управление группами и правами доступа';
$Definition['Manage Routes'] = 'Управление перенаправлениями';
$Definition['Manage Spam'] = 'Управление спамом';
$Definition['Manage Themes'] = 'Управление темами';
$Definition['Manage Users'] = 'Управление пользователями';
$Definition['Manage'] = 'Управление';
$Definition['Managing Categories'] = 'Управление категориями';
$Definition['Mark All Viewed'] = 'Отметить все прочитанным';
$Definition['Media'] = 'Загрузка файлов';
$Definition['Max Comment Length'] = 'Максимальное количество символов в отзыве:';
$Definition['Member roles'] = 'Отметить все группы, которые применяются к новым или подтвержденным пользователям.';
$Definition['Member'] = 'Участник';
$Definition['Message'] = 'Сообщение';
$Definition['message'] = 'сообщение';
$Definition['Messages can appear anywhere in your application.'] = 'Сообщения могут быть отображены в любом месте сайта пользователя.<br>Например, это может быть использовано для информирования о новостях.<br>На этой странице можно управлять ими с помощью перетаскивания.';
$Definition['Messages'] = 'Сообщения';
$Definition['Method'] = 'Способ';
$Definition['minute(s)'] = 'Минут(ы)';
$Definition['Moderation Queue'] = 'Отложеная модерация';
$Definition['Moderation'] = 'Модерирование';
$Definition['Moderator'] = 'Модератор';
$Definition['More'] = 'Ещё';
$Definition['Most recent by %1$s'] = 'Последнее сообщение от %1$s';
$Definition['Most recent: %1$s by %2$s'] = 'Новое: %1$s в %2$s';
$Definition['Move discussions in this category to a replacement category.'] = 'Переместите обсжудение в эту категорию для её замены.';
$Definition['My Bookmarks'] = 'Закладки';
$Definition['My Discussions'] = 'Мои темы';
$Definition['My Drafts'] = 'Мои черновики';
$Definition['My Invitations'] = 'Мои инвайты';
$Definition['My Preferences'] = 'Мои настройки';

$Definition['Name Unavailable'] = 'Имя не доступно';
$Definition['Name'] = 'Имя';
$Definition['Need More Help?'] = 'Нужна помощь?';
$Definition['never'] = 'никогда';
$Definition['New comments in the last day'] = 'Новых комментариев за сутки';
$Definition['New comments in the last week'] = 'Новых комментариев за 7 дней';
$Definition['New conversations in the last day'] = 'Новых разговоров за сутки';
$Definition['New conversations in the last week'] = 'Новых разговоров за 7 дней';
$Definition['New discussions in the last day'] = 'Новых тем за 24 часа';
$Definition['New discussions in the last week'] = 'Новых тем за 7 дней';
$Definition['New messages in the last day'] = 'Новых сообщений за сутки';
$Definition['New messages in the last week'] = 'Новых сообщений за неделю';
$Definition['New Password'] = 'Новый пароль';
$Definition['New users are only registered through SSO plugins.'] = 'Новые пользователи регистрируются с помощью социальных плагинов.';
$Definition['New users are reviewed and approved by an administrator (that\'s you!).'] = 'Новые пользователи рассматриваются и подтверждаются администратором (вами).';
$Definition['New users fill out a simple form and are granted access immediately.'] = 'Новые пользователи регистрируются и получают доступ к созданию тем сразу.';
$Definition['New users in the last day'] = 'Новых пользователей за сутки';
$Definition['New users in the last week'] = 'Новых пользователей за неделю';
$Definition['No default roles.'] = 'Группа по умолчанию настроена неправильно. Кликните сюда: %s, чтобы исправить это.';
$Definition['No discussions were found.'] = 'Не найденно ни одной темы';
$Definition['No Items Selected'] = 'Ничего не выбрано';
$Definition['None'] = 'Нет';
$Definition['Not Authorized (401)'] = 'Не авторизированы (401)';
$Definition['Not Found (404)'] = 'Не найдена (404)';
$Definition['Not Spam'] = 'Это не спам';
$Definition['Notes'] = 'Примечание';
$Definition['Notifications'] = 'Сообщения';
$Definition['Not much happening here, yet.'] ='Пока ещё ничего не произошло.';
$Definition['Notify me of private messages.'] = 'Сообщать о новых личных сообщениях.';
$Definition['Notify me when I am added to private conversations.'] = 'Сообщать, когда меня добавляют к приватному разговору.';
$Definition['Notify me when people comment on my bookmarked discussions.'] = 'Сообщать о комментариях в темах из закладок.';
$Definition['Notify me when people comment on my discussions.'] = 'Сообщать о комментариях в моих темах.';
$Definition['Notify me when people mention me in comments.'] = 'Сообщать, когда меня упоминают в комментариях.';
$Definition['Notify me when people mention me in discussion titles.'] = 'Сообщать, когда меня упоминают в названиях тем.';
$Definition['Notify me when people reply to my wall comments.'] = 'Сообщать об ответах на мои отзывы на стене';
$Definition['Notify me when people start new discussions.'] = 'Уведомлять <b>о всех новых</b> темах';
$Definition['Notify me when people write on my wall.'] = 'Сообщать о новых надписях на стене';

$Definition['OK'] = 'OK';
$Definition['Okay'] = 'Да';
$Definition['Old Password'] = 'Старый пароль';
$Definition['OldPassword'] = 'Старый пароль';
$Definition['on'] = 'на';
$Definition['On'] = 'На';
$Definition['One or more users have left this conversation.'] = 'Пользователи перешли к другому обсуждению. Они не будут получать сообщения до тех пор, пока Вы снова не отправите им приглашение.';
$Definition['Only Allow Each User To Post'] = 'Разрешить пользователям создавать не более';
$Definition['OpenID Settings'] = 'Настройки OpenID';
$Definition['Options'] = 'Настройки';
$Definition['Or Spamblock For'] = 'или заблокировать на';
$Definition['Or you can...'] = 'Или...';
$Definition['Organize Categories'] = 'Сортировка категорий';
$Definition['Original'] = 'Оригинал';
$Definition['Other Themes'] = 'Другие темы';
$Definition['Outgoing Email'] = 'Исходящая почта';

$Definition['Page'] = 'Страница';
$Definition['Page Not Found'] = 'Страница не найдена';
$Definition['PageDetailsMessage'] = '%1$s для %2$s';
$Definition['PageDetailsMessageFull'] = '%1$s для %2$s из %3$s';
$Definition['PageViews'] = 'Просмотров';
$Definition['Panel Box'] = 'Панель уведомлений';
$Definition['Password Options'] = 'Настройки пароля';
$Definition['Password'] = 'Пароль';
$Definition['Passwords don\'t match'] = 'Пароль не подходит';
$Definition['Pending'] = 'В ожидании';
$Definition['Permalink'] = '#';
$Definition['permalink'] = 'ссылка';
$Definition['Permanent (301)'] = 'Перемещено (301)';
$Definition['Permission.Category'] = 'Категория';
$Definition['Please choose an authenticator to configure.'] = 'Выберите плагин аутентификации для настройки';
$Definition['Please Confirm'] = 'Подтвердите пожалуйста';
$Definition['Please wait while you are redirected. If you are not redirected, click <a href="%s">here</a>.'] = 'Пожалуйста, подождите пока вы будете перенаправленны. Если в течении 10 секун ничего не произошло, кликните <a href="%s">тут</a>.';
$Definition['Plugin.Enabled'] = 'Включены';
$Definition['Plugin'] = 'Плагин';
$Definition['PluginHelp'] = 'Плагины позволяют изменять функциональность вашего сайта<br />Для установки плагина поместите его в папку %s и затем активируйте на этой странице';
$Definition['Plugins'] = 'Плагины';
$Definition['Popular Discussions'] = 'Популярные темы';
$Definition['Popular'] = 'Популярные';
$Definition['Position'] = 'Позиция';
$Definition['Post Comment'] = 'Отправить';
$Definition['Post Discussion'] = 'Отправить';
$Definition['Powered by Vanilla'] = '';//'Vanilla Россия. <a href="http://vanillaforums.org">Vanillaforums.org</a>';
$Definition['Prevent spam on your forum by limiting the number of discussions &amp; comments that users can post within a given period of time.'] = 'Избегайте спама и флуда на форуме, путем ограничения количества новых обсужедний и комментариев в единицу времени.';
$Definition['Preview'] = 'Просмотр';
$Definition['Private Key'] = 'Приватный ключ';
$Definition['Proceed'] = 'Продолжить';
$Definition['Profile Picture'] = 'Изображение профиля';
$Definition['Profiles'] = 'Профили';
$Definition['Public Key'] = 'Публичный ключ';

$Definition['Quick-Start Guide to Creating Themes for Vanilla'] = 'Краткое руководство по созданию тем для Vanill\'ы';

$Definition['Reason'] = 'Причина';
$Definition['Recent Activity'] = 'Последняя активность';
$Definition['Recent News'] = 'Последние новости';
$Definition['Recent Tutorials'] = 'Новые уроки';
$Definition['Recently Active Users'] = 'Последние активные пользователи';
$Definition['Recipients'] = 'Получатели';
$Definition['Redirecting...'] = 'Перенаправление...';
$Definition['Refresh Comments'] = 'Обновить отзывы';
$Definition['RecipientUserID'] = 'ID получателя';
$Definition['Registration'] = 'Регистрация';
$Definition['Remember me on this computer'] = 'Запомнить точку входа';
$Definition['Remove Banner Logo'] = 'Удалить логотип';
$Definition['Remove locale developer files.'] = 'Удалить локальные файлы разработки и сбросить изменения';
$Definition['Remove My Picture'] = 'Удалить мое изображение';
$Definition['Remove Picture'] = 'Удалить изображение';
$Definition['Remove'] = 'Удалить';
$Definition['Reopen'] = 'Открыть снова';
$Definition['Reply'] = 'Ответить';
$Definition['Replacement Category'] = 'Замена категории';
$Definition['Reported by: '] = 'Об этом сообщает: ';
$Definition['Request a new password'] = 'Выслать новый пароль.';
$Definition['Require users to confirm their email addresses (recommended)'] = 'Запрашивать у пользователей подтверждение по email (рекомендуется)';
$Definition['Requires: '] = 'Необходимо: ';
$Definition['Rescan'] = 'Сканировать';
$Definition['Reset password and send email notification to user'] = 'Сбросить пароль и отправить пользователю на почту сообщение об этом';
$Definition['Reset Password'] = 'Сбросить пароль';
$Definition['Restore'] = 'Восстановить';
$Definition['Reveal Password'] = 'Показать пароль';
$Definition['Role Name'] = 'Имя группы';
$Definition['Role'] = 'Группа';
$Definition['RoleID'] = 'ID Группы';
$Definition['Roles & Permissions'] = 'Группы и права доступа';
$Definition['Roles determine user\'s permissions.'] = 'Каждый пользователь сайта состоит минимум в одной группе. Настройка групп определяет, что пользователь, состоящий в группе, может делать на сайте';
$Definition['Roles'] = 'Группы';
$Definition['Route Expression'] = 'Формирование перенаправления';
$Definition['Route'] = 'Перенаправление';
$Definition['Routes can be used to redirect users to various parts of your site depending on the url.'] = 'Перенаправления могут быть использованы для автоматической переадресации пользователей на различные части вашего сайта в зависимости от URL.';
$Definition['Routes'] = 'Перенаправления';
$Definition['Run structure & data scripts'] = 'Запустить обновление структуры и скриптов';

$Definition['Save Comment'] = 'Сохранить исправления';
$Definition['Save Draft'] = 'Сохранить черновик';
$Definition['Save Preferences'] = 'Сохранить настройки';
$Definition['Save'] = 'Сохранить';
$Definition['Saved'] = 'Сохранено.';
$Definition['Search by user or role.'] = 'Поиск по имени пользователя или группе';
$Definition['Search'] = 'Поиск';
$Definition['seconds'] = 'секунд';
$Definition['Security Check'] = 'Проверка безопасности';
$Definition['Select an image on your computer (2mb max)'] = 'Выберите изображение на вашем компьютере (Максимум 2Мб)';
$Definition['Select the file to import'] = 'Выберите файл для импорта';
$Definition['Send %s a Message'] = 'Отправить %s сообщение';
$Definition['Send Again'] = 'Повторно отправить';
$Definition['Send Message'] = 'Отправить сообщение';
$Definition['Sent To'] = 'Отправить';
$Definition['Settings'] = 'Настройки';
$Definition['Share'] = 'Поделиться';
$Definition['she'] = 'она';
$Definition['Sign In with Google'] = 'Войти с помощью Google';
$Definition['Sign In with OpenID'] = 'Войти с помощью OpenID';
$Definition['Sign In with Twitter'] = 'Войти с помощью Twitter';
$Definition['Sign In'] = 'Войти';
$Definition['Sign Out'] = 'Выйти';
$Definition['Sign Up'] = 'Зарегистрироваться';
$Definition['SignIn'] = 'Вход';
$Definition['Sink'] = 'Пауза';
$Definition['SMTP Host'] = 'SMTP сервер';
$Definition['SMTP Password'] = 'SMTP Пароль';
$Definition['SMTP Port'] = 'SMTP Порт';
$Definition['SMTP Security'] = 'Протокол безопасности SMTP';
$Definition['SMTP User'] = 'SMTP Пользователь';
$Definition['somebody'] = 'кто-то';
$Definition['Spam'] = 'Спам';
$Definition['SSL'] = 'SSL';
$Definition['Start a New Conversation'] = 'Начать новый диалог';
$Definition['Start a New Discussion'] = 'Начать новую тему';
$Definition['Start Conversation'] = 'Начать обсуждение';
$Definition['Started by %1$s'] = 'Опубликовал %1$s';
$Definition['Statistics'] = 'Статистика';
$Definition['Status'] = 'Статус';

$Definition['Take Action'] = 'Принять меры';
$Definition['Target'] = 'Расположение';
$Definition['Tell us why you want to join!'] = 'Расскажите, почему вы решили присоединиться к нам!';
$Definition['Temporary (302)'] = 'Temporary (302)';
$Definition['The %s Authenticator does not have any custom configuration options.'] = 'Плагин %s не использует индивидуальные настройки';
$Definition['The addon could not be enabled because it generated a fatal error: <pre>%s</pre>'] = 'Аддон не может быть активирован потому, что он выдает фатальную ошибку: <pre>%s</pre>';
$Definition['The banner logo appears at the top of your forum.'] = 'Логотип отображается на всех страницах и заменяет название форума на загруженное изображение.';
$Definition['The banner title appears on the top-left of every page. If a banner logo is uploaded, it will replace the banner title on user-facing forum pages.'] = 'Название сайта отображается на каждой странице в левом верхнем углу (в зависимости от выбранной темы оформления), а также в заголовках страниц.<br><br>Кроме того, вы можете загрузить логотип, вместо названия сайта.';
$Definition['The email you entered in use by another member.'] = 'Пользователь с таким Email уже существует.';
$Definition['The email you have entered is already related to an existing account.'] = 'Этот Email уже привязан к существующему акаунту.';
$Definition['The file failed to upload.'] = 'Невозможно загрузить файл.';
$Definition['The following content has been flagged by users for moderator review.'] = 'Контент, который пометили пользователи для проверки модератором.';
$Definition['The homepage was saved successfully.'] = 'Страница была успешно сохранена.';
$Definition['The page you were looking for could not be found.'] = 'Страница, которую вы ищете, не существует.';
$Definition['The user and all related content has been deleted.'] = 'Пользователь и вся информация, которая была с ним связана, удалены.';
$Definition['The user content will be completely deleted.'] = 'Контент пользователя будет полностью удалён';
$Definition['The user has been deleted.'] = 'Пользователь был удален.';
$Definition['The Vanilla 2 Exporter'] = 'Экспорт в Vanilla 2';
$Definition['ThemeHelp'] = 'Темы позволяют изменить внешний вид вашего сайта.<br />Загрузите папку с темой сюда: %s и активируйте её на этой странице.';
$Definition['Themes'] = 'Темы оформления';
$Definition['Theming Overview'] = 'Информация о темах';
$Definition['There are currently no applicants.'] = 'В настоящее время нет пользователей ожидающих одобрения участия на форуме.';
$Definition['There are no database structure changes required. There may, however, be data changes.'] = 'Никаких изменений в структуре базы данных не требуется. Однако, изменения уже могли быть сделаны.';
$Definition['There are no items awaiting moderation at this time.'] = 'В настоящее время нет новых сообщений для модерации.';
$Definition['This action cannot be undone.'] = 'Это действие не может быть отменено.';
$Definition['This category is archived.'] = 'Эта категория находится в архиве.';
$Definition['This category has custom permissions.'] = 'Эта категория содержит особые права.';
$Definition['This discussion has been closed.'] = 'Эта тема была закрыта.';
$Definition['This image has been resized to fit in the page. Click to enlarge.'] = 'Изображение было уменьшено. Кликните для увеличения.';
$Definition['This plugin allows users to sign in with OpenID. <b>Make sure you click Settings after enabling this plugin to enable OpenID signin</b>.'] = 'Этот плагин позволяет пользователю войти на сайт с помощью OpenID. <b>После активации перейдите в настройки чтобы включить вход по OpenID</b>.';
$Definition['This plugin allows users to sign in with their Google accounts. <b>Make sure you click Settings after enabling this plugin to enable Google signin</b>.'] = 'Этот плагин позволяет пользователю войти на сайт с помощью Google. <b>После активации перейдите в настройки чтобы включить вход с помощью Google аккаунта.</b>.';
$Definition['This plugin helps locale package development.'] = 'Этот плагин помогает сделать локализацию. Плагин поддерживает <code>%s</code>.';
$Definition['This user has not commented yet.'] = 'Этот пользователь ничего не комментировал.';
$Definition['This user has not made any discussions yet.'] = 'Этот пользователь не создал ни одной темы.';
$Definition['Thumbnail'] = 'Миниатюра';
$Definition['TLS'] = 'TLS';
$Definition['To embed your Vanilla community forum into a remote web application, use the forum embed code or one of the forum embed plugins below.'] = 'Для интеграции вашего форума на другие сайты, используйте код вставки форума или вставьте его с помощью плагинов описаных ниже.';
$Definition['To send another confirmation email click <a href="%s">here</a>.'] = 'Для того, чтобы послать другое письмо активации, кликните <a href="%s">здесь</a>.';
$Definition['To use reCAPTCHA you must get an API key from %s'] = 'Для использования системы reCAPTCHA вы должны получить API ключ на сайте %s';
$Definition['Tools'] = 'Инструменты';
$Definition['Transport error: %s'] = 'Произошла ошибка при обработке запроса <br /> сервер вернул следующее сообщение: %s';
$Definition['Twitter Connect allows users to sign in using their Twitter account.'] = 'Подключение через Twitter позволяет пользователю войти на форум с помощью twitter аккаунта. <b>Необходима активация приложения на сайте Twitter\'a чтобы плагин работал правильно.</b>';
$Definition['Twitter Settings'] = 'Настройки входа через Twitter';
$Definition['Type'] = 'Тип';

$Definition['Unannounce'] = 'Убрать объявление';
$Definition['Unbookmark'] = 'Убрать из закладок';
$Definition['Uninvite'] = 'Отменить приглашение';
$Definition['Unlimited'] = 'Не ограничено';
$Definition['Unsink'] = 'Открыть';
$Definition['Upload'] = 'Загрузить';
$Definition['UrlCode'] = 'Код ссылки';
$Definition['Url'] = 'Url';
$Definition['Use an SMTP server to send email'] = 'Использовать SMTP-сервер для отправки писем';
$Definition['Use categories to organize discussions'] = 'Использовать категории для организации общения';
$Definition['Use Categories'] = 'Использовать категории';
$Definition['Use My Current Password'] = 'Использовать мой текущий пароль';
$Definition['Use the content at this url as your homepage.'] = 'Главная страница, которую посетители будут видеть, заходя по адресу <b>%s</b>. По умолчанию показываются "Все темы", но вы можете использовать всё, что захотите. Вот несколько популярных вариантов: ';
$Definition['User Count'] = 'Кол-во пользователей';
$Definition['User Deleted'] = 'Пользователь удален';
$Definition['User Registration Settings'] = 'Настройки регистрации пользователей';
$Definition['Username can only contain letters, numbers, underscores, and must be between 3 and 20 characters long.'] = 'Имена пользователей могут содержать только буквы, цифры и знаки подчеркивания и должны быть в диапазоне от 3 до 20 символов.';
$Definition['Users will be assigned to this role until they\'ve confirmed their email addresses.'] = 'Пользователи будут прикреплены к этой группе после подтверждения своего email';
$Definition['UsernameError'] = 'Имена пользователей могут содержать только буквы, цифры и знаки подчеркивания и должны быть в диапазоне от 3 до 20 символов.';
$Definition['User'] = 'Пользователь';
$Definition['Username'] = 'Имя'; //Ник
$Definition['Users'] = 'Пользователи';

$Definition['ValidateBanned'] = '%s не доступно.'; 
$Definition['ValidateBoolean'] = '%s - неправильный ответ';
$Definition['ValidateConnection'] = 'При указанных параметрах не может быть установлено соеденение с базой данных. База данных отдает следующие ошибки: <code>%s</code>';
$Definition['ValidateDate'] = '%s - неправильная дата.';
$Definition['ValidateDecimal'] = '%s - неверная дробь.';
$Definition['ValidateEmail'] = '%s некорректный адрес почты.';
$Definition['ValidateEnum'] = '%s неправильно.';
$Definition['ValidateInteger'] = '%s - не целое число (целое число).';
$Definition['ValidateLength'] = '%1$s с %2$s символов слишком много.';
$Definition['ValidateMatch'] = 'Поля "%s" не совпадают.';
$Definition['ValidateMinimumAge'] = 'Чтобы продолжить, Вам должно быть не менее 16 лет.';
$Definition['ValidateOneOrMoreArrayItemRequired'] = 'Вы должны выбрать хотя бы один %s';
$Definition['ValidateRegex'] = '%s не является корректным.';
$Definition['ValidateRequired'] = 'Вы не заполнили поле %s.';
$Definition['ValidateRequiredArray'] = 'Вы должны выбрать хотя бы один %s.';
$Definition['ValidateTime'] = '%s - неверное время.';
$Definition['ValidateTimestamp'] = '%s - неправильная метка времени.';
$Definition['ValidateUsername'] = 'Имя пользователя может быть от 3 до 20 символов, и должно содержать только буквы латинского алфавита, цифры и символ подчеркиания.';
$Definition['ValidateVersion'] = '%s отображает неверный номер версии. Правильный номер смотри в функции version_compare() на php.';
$Definition['Vanilla.Archive.Description'] = 'Вы можете выбрать архивацию тем на форуме старше определенной даты. Архивированные темы практически закрываются, что не позволяет добавить в них новые посты.';
$Definition['Vanilla.Categories.MaxDisplayDepth'] = 'Сколько уровней вложенности отображать в категориях: %1$s';
$Definition['Vanilla'] = 'Vanilla';
$Definition['version %s'] = 'версия %s';
$Definition['Version %s'] = 'Версия %s';
$Definition['Version'] = 'Версия';
$Definition['View Page'] = 'Показать страницу';
$Definition['View'] = 'Просмотр';
$Definition['Views'] = 'Визитов';
$Definition['Visit Site'] = 'Перейти на сайт';
$Definition['Visits'] = 'Посещений';
$Definition['Vote'] = 'Голосовать';
$Definition['Votes'] = 'Рейтинг';

$Definition['wall'] = 'Стена';
$Definition['Warning: Loading tables can be slow.'] = '<b>Внимание</b>! Конфигурация сервера не поддерживает быструю загрузку. Если вы пытаетесь импортировать большой файл (> 200.000 комментариев), то вам лучше обновить конфигурацию сервера. Для более подробной информации <a href="http://vanillaforums.com/porter">перейдите сюда</a>';
$Definition['Warning: This is for advanced users.'] = '<b>Внимание</b>: Это сообщение адресовано продвинутым пользователям и требует, чтобы Вы внесли дополнительные изменения на вашем веб-сервере. Это возможно, если у Вас есть выделенный или виртуальный хостинг. Ничего не предпринимайте, если не знаете, что необходимо делать.';
$Definition['Warning'] = 'Внимание!';
$Definition['We will attempt to use the local mail server to send email by default. If you want to use a separate SMTP mail server, you can configure it below.'] = 'Мы попытаемся использовать локальный почтовый сервер для отправки электронной почты по умолчанию. Если вы хотите использовать отдельный почтовый SMTP сервер, настройте его ниже.';
$Definition['Welcome Aboard!'] = 'Добро пожаловать к нам!';
$Definition['What\'s the Buzz?'] = 'Какие новости?';
$Definition['Why do you want to join?'] = 'Почему вы хотите зарегистрироваться?';
$Definition['Within'] = 'В пределах';
$Definition['WordPress Plugin'] = 'Плагин для WordPress';
$Definition['Write a comment'] = 'Написать комментарий';
$Definition['Write Comment'] = 'Комментировать';
$Definition['Write something about yourself...'] = 'Написать что-нибудь о себе...';

$Definition['You are connected as %s through %s.'] = 'Вы подключитесь %s через %s.';
$Definition['You are previewing the %s theme.'] = 'Вы просматриваете тему %s';
$Definition['You can always use your password at<a href="%1$s">%1$s</a>.'] = 'Вы всегда можете использовать пароль на <a href="%1$s">%1$s</a>.';
$Definition['You can make the categories page your homepage.'] = 'Страницы категорий можно установить в качестве главной страницы <a href="%s">тут</a>.';
$Definition['You can place files in your /uploads folder.'] = 'Если Ваш файл слишком велик чтобы загрузить его напрямую — залейте его в папку /uploads.<br>Затем вы можете начать <b>экспорт</b> в файлы с расширением: <b>.txt, .gz</b>.';
$Definition['You cannot edit the thumbnail of an externally linked profile picture.'] = 'Вы не можете редактировать изображение в профиле';
$Definition['You do not have any conversations.'] = 'Вы необщительны.';
$Definition['You do not have any drafts.'] = 'У вас нет ни одного черновика.';
$Definition['You do not have any notifications yet.'] = 'У вас нет сообщений.';
$Definition['You have %s invitations left for this month.'] = 'У Вас %s пропущенных приглашений в этом месяце.';
$Definition['You cannot disable the %1$s plugin because the %2$s plugin requires it in order to function.'] = 'Плагин %1$s нельзя отключить потому, что он необходим для стабильной работы плагина %2$s.';
$Definition['You cannot disable the OpenID plugin because the GoogleSignIn plugin requires it in order to function.'] = 'Плагин OpenID нельзя отключить потому, что он необходим для стабильной работы плагина GoogleSignIn.';
$Definition['You must agree to the terms of service.'] = 'Ви не прийняли правила користування.';
$Definition['You have posted %1$s times within %2$s seconds. A spam block is now in effect on your account. You must wait at least %3$s seconds before attempting to post again.'] = 'Вы написали %1$s сообщений за %2$s секунд. В целях борьбы со спамом вы заблокированы на %3$s секунд, по прошествии которых вы снова сможете публиковать сообщения.';
$Definition['You must agree to the terms of service.'] = 'Вы должны подтвердить правила использования форума.';
$Definition['You need to confirm your email address.'] = 'Вам нужно подтвердить email адрес. Для того, чтобы повторно послать письмо активации, кликните <a href="/entry/emailconfirmrequest">здесь</a>.';
$Definition['you'] = 'я';
$Definition['You'] = 'я';
$Definition['Your application will be reviewed by an administrator. You will be notified by email if your application is approved.'] = 'Спасибо. Ваша заявка будет рассмотрена администратором. Вы будете уведомлены по электронной почте, если ваша заявка будет одобрена.';
$Definition['Your changes have been saved successfully.'] = 'Ваши изменения были успешно сохранены.';
$Definition['Your changes have been saved.'] = 'Ваши изменения сохранены.';
$Definition['Your changes were saved.'] = 'Ваши изменения сохранены.';
$Definition['Your old password was incorrect.'] = 'Неверно введен старый пароль';
$Definition['Your registered username: <strong>%s</strong>'] = 'Ваше имя при регистрации: <strong>%s</strong>';
$Definition['Your settings have been saved successfully.'] = 'Ваши настройки были успешно сохранены.';
$Definition['Your default locale won\'t display properly'] = 'Ваше местоположение по умолчанию не отображается правильно. Пожалуйста, выберите его: %s.';
$Definition['Your email has been successfully confirmed.'] = 'Ваш email был успешно подтвержден.';
$Definition['Your preferences have been saved.'] = 'Ваши настройки были сохранены.';
$Definition['Your request has been sent.'] = 'Ссылка для подтверждения выслана вам на почту, пожалуйста проверьте.';
$Definition['Your settings have been saved.'] = 'Ваши настройки были сохранены.';
$Definition['your'] = 'ваше';

// Установка Vanilla
$Definition['Database Host'] = 'Хост базы данных';
$Definition['Database Name'] = 'Имя базы данных';
$Definition['Database User'] = 'Пользователь базы данных';
$Definition['Database Password'] = 'Пароль базы данных';
$Definition['Yes, the following information can be changed later.'] = 'Информация, расположенная ниже, впоследствии может быть изменена.';
$Definition['Application Title'] = 'Название Вашего форума';
$Definition['Admin Email'] = 'E-mail администратора';
$Definition['Admin Username'] = 'Логин администратора';
$Definition['Admin Password'] = 'Пароль администратора';
$Definition['Confirm Password'] = 'Подтвердите пароль';
$Definition['Continue &rarr;'] = 'Дальше &rarr;';
$Definition['Version %s Installer'] = 'Версия %s';
$Definition['Failed to connect to the database with the username and password you entered. Did you mistype them? The database reported: <code>%s</code>'] = 'Ошибка при подключении к базе данных. Неправильно введен логин или пароль. Проверьте, правильно ли выбран язык ввода данных. <code>%s</code>';
$Definition['The database user you specified does not have permission to access the database. Have you created the database yet? The database reported: <code>%s</code>'] =  'Пользователь базы данных, имя которого Вы указали, не имеет достаточных полномочий для доступа к БД. Попробуйте создать новую базу данных. <code>%s</code>';
$Definition['It appears as though the database you specified does not exist yet. Have you created it yet? Did you mistype the name? The database reported: <code>%s</code>'] = 'Базы данных с таким именем не существует. Возможно Вы ошиблись при наборе или попробуйте создать новую БД. <code>%s</code>';
$Definition['Are you sure you\'ve entered the correct database host name? Maybe you mistyped it? The database reported: <code>%s</code>'] = 'Вы уверены, что правильно ввели имя хоста базы данных? Возможно Вы ошиблись при наборе. <code>%s</code>';
$Definition['You must specify an admin username.'] = 'Вы не ввели имя администратора';
$Definition['You must specify an admin password.'] = 'Вы не ввели пароль администратора';
$Definition['You are running PHP version %1$s. Vanilla requires PHP %2$s or greater. You must upgrade PHP before you can continue.'] = 'Для работы форума необходима %2$s версия PHP или выше. У Вас установлена %1$s версия PHP. Поэтому, прежде чем продолжать установку, обновите PHP.';
$Definition['You must have the PDO module enabled in PHP in order for Vanilla to connect to your database.'] = 'Включите в PHP модуль PDO для подключения к базе данных.';
$Definition['You must have the MySQL driver for PDO enabled in order for Vanilla to connect to your database.'] = 'Для подключения к базе данных у Вас должны быть включены драйвера MySQL.';
$Definition['Some folders don\'t have correct permissions.'] = '<p>Неправильно указаны права доступа у некоторых каталогов.</p><p>С помощью ftp клиента или с помощью командной строки сделайте следующие настройки для продолжения установки:</p>';
$Definition['Your configuration file does not have the correct permissions. PHP needs to be able to read and write to this file: <code>%s</code>'] = 'Неправильно установлены права для файла конфигурации. PHP должен иметь возможность чтения и записи в файл: <code>%s</code>';
$Definition['Vanilla is installed!'] = 'Форум Vanilla установлен!';
$Definition['You are missing Vanilla\'s <b>.htaccess</b> file. Sometimes this file isn\'t copied if you are using ftp to upload your files because this file is hidden. Make sure you\'ve copied the <b>.htaccess</b> file before continuing.'] = 'Нет файла <b>.htaccess</b>. Возможно при копировании на ftp Вы упустили этот файл, т.к. он помечен атрибутом "невидимый". Без этого файла Вы не сможете продолжить установку форума.';
$Definition['You are missing Vanilla\'s .htaccess file.'] = 'Нет файла <b>.htaccess</b>. Возможно при копировании на ftp Вы упустили этот файл, т.к. он помечен атрибутом "невидимый". Без этого файла Вы не сможете продолжить установку форума.';
$Definition['Install Vanilla without a .htaccess file.'] = 'Установите Vanilla без файла .htaccess';
$Definition['Try Again'] = 'Повторите';
$Definition['Click here to carry on to your dashboard'] = 'Нажмите для перехода к панели управления сайтом';

// Начало работы. Настройки форума
$Definition['Here\'s how to get started:'] = 'Приступаем к работе:';
$Definition['Welcome to your Dashboard'] = 'Добро пожаловать в панель управления';
$Definition['This is the administrative dashboard for your new community. Check out the configuration options to the left: from here you can configure how your community works. <b>Only users in the "Administrator" role can see this part of your community.</b>'] = 'Это административная панель Вашего нового сообщества. В левой части панели Вы можете настроить параметры конфигурации. <b>Сделать это могут только пользователи с привилегиями "Администратора".</b>';
$Definition['Where is your Community Forum?'] = 'Расположение форума';
$Definition['Access your community forum by clicking the "Visit Site" link on the top-left of this page, or by'] = 'Для того чтобы увидеть Ваш форум глазами пользователя, необходимо нажать кнопку "Перейти на сайт", расположенную в левом верхнем углу этой страницы или кликнуть по';
$Definition['clicking here'] = 'ссылке';
$Definition['The community forum is what all of your users &amp; customers will see when they visit']='Все пользователи, приходящие на форум, будут перенаправлены на URL адрес';
$Definition['Organize your Categories'] = 'Сортировка ваших категорий';
$Definition['Discussion categories are used to help your users organize their discussions in a way that is meaningful for your community.'] = 'Категории помогают упростить навигацию по форуму, а также разбить темы на группы. Например, темы про мотоциклы нужно отнести в категорию "мото", а про автомобили — в категорию "авто"';
$Definition['Customize your Public Profile'] = 'Изменение вашего профиля';
$Definition['Everyone who signs up for your community gets a public profile page where they can upload a picture of themselves, manage their profile settings, and track cool things going on in the community. You should'] = 'Авторизованный пользователь форума получает страницу профиля, где он может разместить информацию о себе, свою фотографию и изменить';
$Definition['customize your profile now'] = 'настройки профиля';
$Definition['Start your First Discussion'] = 'Ваша первая тема';
$Definition['Get the ball rolling in your community by'] = 'Для того чтобы начать новую тему, нажмите на';
$Definition['starting your first discussion'] = 'иконку';
$Definition['now.'] = 'в правом верхнем углу.';
$Definition['Manage your Plugins'] = 'Управление вашими плагинами';
$Definition['Change the way your community works with plugins. We\'ve bundled popular plugins with the software, and there are more available online.'] = 'Настройте Ваш форум, используя встроенные расширения и плагины, расположенные на сайте форума.';

// Кто в сети?
$Definition['Who\'s Online'] = 'Кто сейчас на сайте?';
$Definition['Where should the plugin be shown?'] = 'Где должен быть показан модуль?';
$Definition['Sections'] = 'Разделы';
$Definition['This will show the panel on every page.'] = 'Эта опция будет показывать панель на каждой странице.';
$Definition['This show the plugin on only selected discussion pages'] = 'Эта опция будет показывать панель только на выбранных страницах.';
$Definition['Frequency'] = 'Частота обновлений';
$Definition['In seconds'] = ' в секундах';
$Definition['Rate of refresh'] = '';
$Definition['Who\'s Online Settings'] = 'Настройки "Кто в сети?"';
$Definition['Make me invisible? (Will not show you on the list)'] = 'Сделать меня невидимым? (Не выдавать присутствие на сайте)';

//Импорт данных
$Definition['Advanced Options'] = 'Расширенные опции';
$Definition['Select the import source'] = 'Выберите файл для импорта данных';
$Definition['This Database'] = 'Эту базу данных';
$Definition['Generate import SQL only'] = 'Генерировать только импорт SQL';
$Definition['Start'] = 'Старт';
$Definition['Yes'] = 'Да';
$Definition['No'] = 'Нет';
$Definition['Email Confirmation Role'] = 'Подтверждение прав по email';
$Definition['Discussion'] ='Только на страницах дискуссий';

//Количество постов (Post Count)
$Definition['Posts'] = 'Сообщения';
$Definition['Posts: %s'] = 'Сообщений: %s';

//Файл аплоад
$Definition['Attach a file'] = 'Прикрепить файл';
$Definition['Insert Image'] = 'Вставить картинку в сообщение';

//Цитирование выделенного
$Definition['Quote'] = 'Цитировать';
$Definition['%s said'] = '%s сказали';

//Тэги
$Definition['Tagging'] = 'Тэгирование';
$Definition['Tagged with '] = 'Отмечено тэгами: ';
$Definition['Tags'] = 'Тэги';
$Definition['Edit Tag'] = 'Редактировать тэг';
$Definition['Tagged'] = 'Тэги темы';
$Definition['Popular Tags'] = 'Популярные тэги';
$Definition['Tag Name'] = 'Имя тэга';
$Definition['No items tagged with %s.'] = 'Ничего не отмечено тэгом %s.';
$Definition['Title'] = 'Название';
$Definition['Tags are keywords that users can assign to discussions to help categorize their question with similar questions.'] = 'Пользователи могут добавлять тэги и ключевые слова для улучшения навигации по связаным дискуссиям.';
$Definition['Plugins.Tagging.Enabled'] = 'Плагин "Теги" Включен';
$Definition['%s tags in the system'] = '%s тэгов в системе';
$Definition['Click a tag name to edit. Click x to remove.'] = 'Кликните на имя тэга для редактирования. Кликните на x для удаления.';
$Definition['There are no tags in the system yet.'] = 'В системе ещё нет ни одного тэга.';

//Вопросы и ответы
$Definition['Ask a Question'] = 'Задать вопрос';
$Definition['Question Title'] = 'Заголовок вопроса';
$Definition['You can either ask a question or start a discussion.'] = 'Вы можете задать вопрос или начать новую тему. Выберите, что вы хотите сделать.';
$Definition['Question'] = 'Вопрос';
$Definition['Unanswered Questions'] = 'Вопросы без ответа';
$Definition['Accept'] = 'Одобрить';
$Definition['Accept this answer.'] = 'Это верный ответ.';
$Definition['Reject'] = 'Отклонить';
$Definition['Reject this answer.'] = 'Этот ответ не подходит.';
$Definition['Click accept or reject beside an answer.'] = 'Кликните "Одобрить" или "Отклонить" для того, чтобы ответить на вопрос.';
$Definition['This answer was Accepted.'] = 'Этот ответ подошёл.';
$Definition['This answer was %s.'] = 'Этот ответ был %s';
$Definition['Accepted'] = 'Одобрен(а)';
$Definition['You have answered questions'] = 'У Вас есть вопросы <a href="{/discussions/mine?qna=Answered,url}">с ответами</a>. Одобрите или отклоните их.';

$Definition['This discussion was merged into %s'] = 'Эта тема была перемещена';
$Definition['Category Management'] = 'Менеджер категорий';
$Definition['CategoriesViewingAll'] = 'Вы просматриваете все категории.';
$Definition['CategoriesShowFollowed'] = 'Показать только те категории, на которые я подписан.';
$Definition['CategoriesViewingFollowed'] = 'Вы просматриваете только те категории, на которые подписаны.';
$Definition['CategoriesShowUnfollowed'] = 'Показать категории на которые Вы не подписаны.';
$Definition['Child Categories:'] = 'Дочерняя категория:';
$Definition['Mark Read'] = '<span title="Отметить посты в этой категории прочитанными">Прочитано</span>';
$Definition['Follow'] = 'Подписаться';
$Definition['Unfollow'] = 'Отписаться';

$Definition['Confirm email addresses'] = 'Запрашивать у пользователей подтверждение по email (рекомендуется)';
$Definition['Users will be assigned to this role until they\'ve confirmed their email addresses.'] = 'Пользователи будут отнесены к этой группе, когда подтвердят свой email';

//Плагин статистики
$Definition['Vanilla Statistics'] = 'Настройки статистики';
$Definition['Verified!'] = 'Проверен!';
$Definition['About Vanilla Statistics'] = 'Про этот плагин';
$Definition['Vanilla Statistics Plugin'] = 'Плагин статистики';
$Definition['Statistics Documentation'] = 'Документация';
$Definition['About.VanillaStatistics'] = 'Это очень важный плагин, отправляющий статистику использования вашего форума на сервера Vanillaforums.org. Для того чтобы авторы Vanilla могли сделать движок форума еще лучше. Мы просим Вас не отключать отправку статистических данных';
$Definition['About.DisableStatistics'] = 'Если вам по каким-то причинам необходимо отключить этот плагин, отредактируйте файл config.php, добавив (или изменив) строку<p> <code>$Configuration[\'Garden\'][\'Analytics\'][\'Enabled\'] = FALSE;</code></p>';

$Definition['Vanilla Statistics are currently disabled'] = 'Модуль статистики отключен';
$Definition['Garden.StatisticsDisabled'] = 'Модуль статистики отключен в файле конфигурации.';
$Definition['Garden.StatisticsLocal.Explain'] = 'Форум работает в тестовом режиме, или расположен на частном IP-адресе.<br>По умолчанию форумы, работающие на частных IP-адресах, не отслеживаются.';
$Definition['Garden.StatisticsLocal.Resolve'] = 'Если Вы уверены, что Ваш форум доступен из интернета, Вы можете получить здесь отчёт о статистике.';
$Definition['Garden.StatisticsReadonly.Explain'] = 'Ваш config.php имеет атрибуты "только для чтения". Поэтому форум не сможет автоматически регистрировать InstallationID и InstallationSecret.';
$Definition['Garden.StatisticsReadonly.Resolve'] = 'Для решения возникшей проблемы, назначьте атрибуты 777 вашему файлу conf/config.php.';

$Definition['Take Action:'] = 'Выберите действие:';
$Definition['You have selected %1$s.'] = 'Вы выбрали %1$s.';
$Definition['You have selected %1$s in this discussion.'] = 'Вы выбрали %1$s в этой теме';
$Definition['No results for %s.'] = 'По запросу "<b>%s</b>" ничего не найдено.';
$Definition['Reset my password'] = 'Сбросить мой пароль';
$Definition['Save your password'] = 'Сохранить пароль';
$Definition['A message has been sent to your email address with password reset instructions.'] = 'На ваш email выслано письмо с инструкцией по смене пароля';
$Definition['You already have an account here.'] = 'У вас здесь уже есть аккаунт';
$Definition['The reCAPTCHA value was not entered correctly. Please try again.'] = 'Код проверки введен не верно. Если вам не понятны символы на картинке, нажмите пиктограмму "две стрелки по кругу", чтобы загрузить другие';
$Definition['The Vanilla Statistics plugin turns your forum\'s dashboard into an analytics reporting tool'] = 'Плагин статистики в панели управления Vanilla показывает графики активности на форуме, основываясь на данных, записаных движком форума. Вы можете получить исчерпывающую информацию об этом плагине в его <a href="http://vanillaforums.org/docs/vanillastatistics">документации</a>.';
$Definition['API Status'] = 'API статус';
$Definition['The name you entered is already in use by another member.'] = 'Этот ник уже занят, придумайте другой';

//Стартовое меню
$Definition['Getting Started with Vanilla'] = 'Добро пожаловать в мир Vanilla!';
$Definition['Kick-start your community and increase user engagement.'] = 'Настрой и приглашай пользователей в своё сообщество!';
$Definition['Vanilla is the simplest, most powerful community platform in the world. It\'s super-duper easy to use. Start with this introductory video and continue with the steps below. Enjoy!'] = 'Vanilla — это самая мощная форумная платформа в мире! К тому же она легка в использовании. Вы можете убедиться в этом, посмотрев видеопрезентацию, которую мы подготовили для Вас. Наслаждайтесь!';
$Definition['Customize'] = 'Выделяйся';
$Definition['Define your forum homepage, upload your logo, and grab a theme.'] = 'Вы можете изменить главную страницу, загрузить свой логотип, выбрать и установить тему оформления.';
$Definition['Organize'] = 'Организуй';
$Definition['Create & organize discussion categories and manage your users.'] = 'Создай и рассортируй категории для общения, управляй своими пользователями. ';
$Definition['Advanced Stuff'] = 'Продвинутые штуковины';
$Definition['Embed your community forum into your website to increase engagement!'] = 'Добавь форум на свой сайт, для повышения количества участников.';
$Definition['Change your banner'] = 'Установи собственное лого';
$Definition['Define your forum homepage'] = 'Назначь главную страницу для форума';
$Definition['Embed your forum in your web site'] = 'Добавление форума на ваш сайт';
$Definition['How to use themes'] = 'Используй новые темы';
$Definition['Organize discussion categories'] = 'Сортируй категории';
$Definition['Encourage your friends to join your new community!'] = 'Пригласи своих друзей участвовать в обсуждениях на форуме!';
$Definition['Send Invitations!'] = 'Послать приглашение!';

$Definition['Vanilla Statistic has had an accident :('] = 'Проблемы с загрузкой данных :('; 
$Definition['Permission denied - Token Verification is required, but failed.'] = 'В доступе отказано. Необходимо подтверждение верификации.'; 
$Definition['Day'] = 'По дням';
$Definition['Month'] = 'По месяцам';
$Definition['The graph shown represents incomplete data. It is missing records from the beginning.'] = 'На графике отображены неполные данные.'; 

$Definition['PermissionErrorTitle'] = 'Проблема с доступом';    
$Definition['PermissionErrorMessage'] = 'У вас недостаточно прав для просмотра этой страницы.';
    
$Definition['Bonk'] ='Блин';
$Definition['Something funky happened. Please bear with us while we iron out the kinks.'] = 'Что-то непонятное... Оставайтесь с нами, пока мы не решим эту проблему.';

$Definition['TermsOfService'] = 'Условия использования';
$Definition['TermsOfServiceText'] = '<p>1. Модерация форума. На форуме отсутствует предварительная фильтрация сообщений. Все сообщения пользователей прочитываются модератором форума. В случае нарушения пользователем правил форума, пользователь получает предупреждение или заносится в банлист. Администрация форума не несёт ответственности за информацию, размещённую пользователями.<br>
2. Незнание правил. На форуме запрещена публикация информации, противоречащей данным правилам. Незнание правил не освобождает от ответственности.<br>
3. Публикация информации третьими лицами. Ответственность за информацию, размещённую от лица пользователя, несёт только сам пользователь. Администрация рекомендуeт не допускать использования ваших учётных данных третьими лицами.<br>
4. Аватара. В качестве аватары разрешается использовать любое изображение, не содержащее кровавых сцен, сцен насилия, порнографии. Запрещается использовать аватары, уже используемые другими участниками форума.<br>
5. Реклама. На форуме запрещено заниматься рекламой, в том числе размещать частные объявления.<br>
6. Новые темы. Приветствуется создание интересных тем.<br>
7. Сознательность. Поощряется своевременное оповещение Администрации о нарушении правил форума.</p>';

$Definition['Could not instantiate mail function.'] = 'Не удалось воспользоваться функцией почты.';
$Definition['EmailHeader'] = 'Привет, {User.Name}!
';
$Definition['EmailFooter'] = '
Удачного дня!';
$Definition['EmailInvitation'] = 'Привет!

%1$s приглашает присоединиться %2$s.
Если вы хотите присоедниться, то перейдите по ссылке:
%3$s

Спасибо за внимание!';

$Definition['EmailMembershipApproved'] = 'Привет %1$s,

Вы были приняты. Вы можете войти по этой ссылке:
%2$s

Спасибо за внимание!';

$Definition['EmailNotification'] = '%1$s

Для проверки перейдите по ссылке:
%2$s

Спасибо за внимание!';

$Definition['EmailPassword'] = 'Привет %1$s,

%2$s сбросил ваш пароль в %3$s. Ваши данные:

Email: %6$s
Пароль: %5$s
Ссылка: %4$s

Спасибо за внимание!';

$Definition['EmailStoryNotification'] = '%1$s
%3$s
---------------------------------
Для проверки перейдите по ссылке:
%2$s

Спасибо за внимание!';

$Definition['EmailWelcome'] = 'Привет %1$s,

%2$s создал аккаунт для вас в %3$s. Ваши данные:

Email: %6$s
Пароль: %5$s
Ссылка: %4$s

Спасибо за внимание!';

$Definition['EmailWelcomeConnect'] = 'Привет {User.Name},

Вы присоединились к {Title}. Ваши данные:

Имя пользователя: {User.Name}
Присоединен: {ProviderName}
Сайт доступен по ссылке {/,url,domain}.

Спасибо за внимание!';

$Definition['EmailWelcomeRegister'] = 'Привет {User.Name},

Вы зарегистрировались на {Title}. Ваши данные:

Имя пользователя: {User.Name}
Email: {User.Email}
Сайт доступен по ссылке {/,url,domain}.

Спасибо за внимание!';

$Definition['Hi Pal!'] = 'Привет!

Зацени, я только что создал новый форум. Думаю это будет замечательное место для общения. 

Перейди по ссылке ниже для того чтобы войти';

$Definition['PasswordRequest'] = 'Hallo %1$s,

Кто-то просит сбросить %2$s пароль. Если это были вы, пройдите по ссылке ниже:
%3$s

В противном случае, просто проигнорируйте это письмо

Всего хорошего!';
