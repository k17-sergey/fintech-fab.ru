<?php
/**
 * @var $eventData
 */
?>

<div class="row">
	<h4>Текущее состояние</h4>

	<div>
		<p class="text-info">Количество открытых задач: <?= $eventData->countIssuesOpened ?></p>

		<p class="text-info">Количество задач в работе: <?= $eventData->countIssuesInWork ?></p>
	</div>

	<h4>События</h4>

	<div>
		<ul>
			<?php foreach ($eventData->issuesEvents as $event): ?>

				<li>
					<img width="16" height="16" src="<?= $event->avatar_url ?>">
					<small><?= $event->time ?></small>
					<b><?= $event->actor_login ?></b>

					<p><?= $event->payload ?></p>

				</li>
			<?php endforeach; ?>
		</ul>

	</div>


</div>

