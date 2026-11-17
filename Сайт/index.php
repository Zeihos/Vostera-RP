<?php
require __DIR__ . '/includes/bootstrap.php';
render_header('');
$cfg = site();
?>
<section class="hero container">
    <div class="hero-art"><img src="<?=asset('img/vostera-cover.jpg')?>" alt="VOSTERA ROLEPLAY — фирменная обложка проекта: автомобили, закат и логотип" fetchpriority="high"><div class="hero-art-shade"></div><span class="hero-art-label"><i></i> НОВАЯ РОЛЕВАЯ ВСЕЛЕННАЯ</span><span class="hero-art-corner">VOSTERA / 2026</span></div>
    <div class="hero-copy">
        <div class="hero-text"><div class="eyebrow"><span class="eyebrow-line"></span> ДОБРО ПОЖАЛОВАТЬ В VOSTERA</div><h1>Твоя история <em>начинается здесь.</em></h1><p>Новые знакомства. Большие амбиции. Неожиданные повороты. Создай персонажа, найди своих людей и напиши собственную историю в мире VOSTERA ROLEPLAY.</p><div class="hero-buttons"><a href="/play.php" class="button button-primary">Начать играть <span class="arrow">↗</span></a><a href="/about.php" class="button button-outline">Узнать о проекте <span class="arrow">→</span></a></div></div>
        <div class="hero-side"><div class="hero-side-head"><div class="star-figure">✦</div><span>VOSTERA<br>ROLEPLAY</span></div><p>Не просто игра.<br><b>Твой собственный путь.</b></p><div class="hero-mini-tags"><span>ROLEPLAY</span><span>COMMUNITY</span></div></div>
    </div>
</section>
<section class="section container" id="features">
  <div class="section-head"><div><div class="eyebrow"><span class="eyebrow-line"></span> ВОЗМОЖНОСТИ</div><h2>Место, где оживают <span>истории.</span></h2></div><p>От первых шагов до собственного дела — выбирай свой путь и развивайся вместе с сообществом.</p></div>
  <div class="feature-grid">
    <article class="feature-card"><div class="feature-num">01 / РОЛЬ</div><div class="feature-symbol">✳</div><h3>Твой персонаж</h3><p>Придумай историю, характер и цели. Здесь выбор определяет, кем ты станешь.</p><a href="/about.php" class="text-link">Подробнее <span>↗</span></a></article>
    <article class="feature-card feature-card-accent"><div class="feature-num">02 / МИР</div><div class="feature-symbol">◇</div><h3>Твой стиль</h3><p>Машины, встречи, уличная культура и атмосфера большого игрового мира.</p><a href="/about.php" class="text-link">Подробнее <span>↗</span></a></article>
    <article class="feature-card"><div class="feature-num">03 / ЛЮДИ</div><div class="feature-symbol">✺</div><h3>Твоя команда</h3><p>Находи единомышленников, заводи знакомства и становись частью сообщества.</p><a href="<?=e($cfg['telegram'])?>" rel="noopener noreferrer" target="_blank" class="text-link">Наш Telegram <span>↗</span></a></article>
  </div>
</section>
<section class="section container split-section">
  <div class="split-graphic"><div class="graphic-logo"><img src="<?=asset('img/vostera-avatar.jpg')?>" alt="Аватар VOSTERA ROLEPLAY" loading="lazy"></div><span class="graphic-corner graphic-corner-one">EST. 2026</span><span class="graphic-corner graphic-corner-two">VOSTERA UNIVERSE</span></div>
  <div class="split-copy"><div class="eyebrow"><span class="eyebrow-line"></span> ПОЧЕМУ VOSTERA</div><h2>Не наблюдай за событиями. <span>Создавай их.</span></h2><p>Каждый игровой день — возможность изменить свою историю, встретить новых друзей и поставить следующую большую цель.</p><div class="check-list"><div><span class="check-dot">✓</span><span>Ролевое взаимодействие и собственные истории</span></div><div><span class="check-dot">✓</span><span>Автомобильная атмосфера и стиль</span></div><div><span class="check-dot">✓</span><span>Общение и развитие сообщества</span></div></div><a href="/about.php" class="button button-outline">Больше о VOSTERA <span class="arrow">↗</span></a></div>
</section>
<section class="section container"><div class="section-head"><div><div class="eyebrow"><span class="eyebrow-line"></span> ПЕРВЫЕ ШАГИ</div><h2>Начать проще, <span>чем кажется.</span></h2></div><a class="text-link section-link" href="/play.php">Полная инструкция <span>↗</span></a></div><div class="steps-row"><div class="step"><span>01</span><div><h3>Придумай имя</h3><p>Создай своего персонажа и его историю.</p></div></div><div class="step"><span>02</span><div><h3>Подготовь игру</h3><p>Следи за публикацией официального клиента VOSTERA.</p></div></div><div class="step"><span>03</span><div><h3>Присоединяйся</h3><p>Подключись к серверу после объявления запуска.</p></div></div></div></section>
<section class="section container"><div class="community-banner"><div><div class="eyebrow"><span class="eyebrow-line"></span> НА СВЯЗИ</div><h2>Стань частью <em>VOSTERA.</em></h2><p>Анонсы, новости разработки и жизнь проекта — в наших официальных сообществах.</p></div><div class="community-actions"><a href="<?=e($cfg['vk'])?>" target="_blank" rel="noopener noreferrer" class="button button-light">Мы ВКонтакте ↗</a><a href="<?=e($cfg['telegram'])?>" target="_blank" rel="noopener noreferrer" class="button button-outline">Мы в Telegram ↗</a></div></div></section>
<?php render_footer(); ?>
