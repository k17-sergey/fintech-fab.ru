<?php
/**
 * @var array $issuesData
 */
?>



<div id="issues_accordion">
	<?php foreach ($issuesData as $issue): ?>
		<h3><?= "#{$issue->head->number}  {$issue->head->title}" ?></h3>
		<div>
			<p>
				<img width="48" height="48" src="<?= $issue->avatar_url ?>" style="border-radius:4px;" alt="<?= $issue->head->user_login ?>">
				Создана: <b><?= $issue->head->user_login ?></b><br>
				<small><a href="<?= $issue->head->html_url ?>"> Ссылка на задачу... </a></small>

			</p>

			<div>
				<h4>Комментарии:</h4>
				<ul>
					<?php foreach ($issue->comments as $comment): ?>

						<li>
							<img width="16" height="16" src="<?= $comment->avatar_url ?>">
							<small><?= $comment->time ?></small>
							<a href="<?= $comment->html_url ?>"> <b><?= $comment->user_login ?></b>
								<em><?= " : \"$comment->preview\"" ?></em> </a>

						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div>
				<h4>Коммиты:</h4>
				<ul>
					<?php foreach ($issue->commits as $commit): ?>

						<li>
							<img width="16" height="16" src="<?= $commit->avatar_url ?>">
							<small><?= $commit->time ?></small>
							<b><?= $commit->actor_login ?></b>

							<p><em><?= $commit->message ?></em></p>

						</li>
					<?php endforeach; ?>
				</ul>
			</div>


		</div>

	<?php endforeach; ?>


</div>



