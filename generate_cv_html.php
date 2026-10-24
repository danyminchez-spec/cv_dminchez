<?php
// Function to generate the full index.html from $data
function generate_cv_html(array $data): string {
    $p = $data['personal_info'] ?? [];
    $name = htmlspecialchars($p['name'] ?? 'Personal CV');
    $photo = htmlspecialchars($p['photo'] ?? 'assets/images/FOTO.jpg');
    $titles = $p['titles'] ?? [];
    $emails = htmlspecialchars($p['emails'] ?? '');
    $phone = htmlspecialchars($p['phone'] ?? '');
    $gitUrl = htmlspecialchars($p['github_url'] ?? 'https://dminchez.github.io/cv_dminchez');
    $gitLabel = htmlspecialchars($p['github_label'] ?? ('Link CV: ' . $gitUrl));

    $profile = nl2br(htmlspecialchars($data['profile'] ?? ''));

    ob_start();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title><?= $name ?> | Personal CV</title>
    <!-- Meta -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CV Profesional de <?= $name ?>">
    <link rel="shortcut icon" href="media/bg/FOTO.webp">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

    <!-- Google Font: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <!-- Theme CSS -->
    <link id="theme-style" rel="stylesheet" href="assets/css/styles.css">

    <style>
        /* Tipografía Poppins en todo el documento */
        body, h1, h2, h3, h4, h5, h6, .name, .title, p, span, a, li, div, small, strong, em, b, i, .badge, .btn {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        }

        /* Encabezado: Centrado, elegante y moderno */
        .resume-header {
            background: linear-gradient(135deg, #1e4f8a 0%, #2b6cb0 50%, #3182ce 100%) !important;
            padding: 0 !important;
            border-radius: 6px 6px 0 0;
            overflow: hidden;
            box-shadow: inset 0 -1px 0 rgba(255,255,255,0.1);
        }

        .resume-header .media {
            align-items: stretch !important;
            margin: 0 !important;
        }

        .header-photo-wrapper {
            width: 220px;
            min-width: 220px;
            max-width: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #173860;
            overflow: hidden;
            position: relative;
        }

        .header-photo-wrapper img.picture {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            object-position: center 20% !important;
            display: block;
            margin: 0 !important;
            transition: transform 0.3s ease;
        }

        .header-photo-wrapper:hover img.picture {
            transform: scale(1.03);
        }

        .header-content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 28px 24px;
            width: 100%;
        }

        .header-content-wrapper h1.name {
            font-size: 32px !important;
            font-weight: 700 !important;
            letter-spacing: 0.8px !important;
            margin: 0 0 8px 0 !important;
            color: #ffffff !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.18);
            line-height: 1.25 !important;
        }

        .header-titles {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 12px;
        }

        .header-titles .title-line {
            font-size: 16.5px !important;
            font-weight: 500 !important;
            color: rgba(255, 255, 255, 0.95) !important;
            letter-spacing: 0.2px;
            line-height: 1.35;
        }

        .header-contact-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 8px 22px;
            margin: 0 0 14px 0 !important;
            padding: 0 !important;
            list-style: none !important;
            color: rgba(255, 255, 255, 0.92);
            font-size: 13.5px;
            font-weight: 400;
        }

        .header-contact-list li {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0 !important;
        }

        .header-contact-list i {
            color: #93c5fd;
            font-size: 15px;
        }

        .github-pill-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.38);
            color: #ffffff !important;
            padding: 6px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none !important;
            transition: all 0.25s ease;
            backdrop-filter: blur(6px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }

        .github-pill-link:hover {
            background: rgba(255, 255, 255, 0.28);
            border-color: rgba(255, 255, 255, 0.65);
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(0,0,0,0.2);
            color: #ffffff !important;
        }

        .github-pill-link i {
            font-size: 15px;
        }

        /* Mejora en títulos de sección y subtítulos */
        .resume-section-title {
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
            color: #2b6cb0 !important;
            border-bottom: 2px solid #e2e8f0;
        }

        .resume-position-title {
            font-weight: 600 !important;
            font-size: 17px !important;
            color: #1e293b !important;
        }

        .resume-company-name {
            font-weight: 500 !important;
            color: #475569 !important;
        }

        .resume-skills-cat {
            font-weight: 700 !important;
            letter-spacing: 0.3px;
        }

        /* Adaptación en pantallas pequeñas */
        @media (max-width: 767.98px) {
            .resume-header .media {
                flex-direction: column !important;
            }
            .header-photo-wrapper {
                width: 150px;
                min-width: 150px;
                max-width: 150px;
                height: 150px;
                border-radius: 50%;
                margin: 24px auto 10px auto;
                border: 4px solid rgba(255,255,255,0.35);
                box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            }
            .header-content-wrapper {
                padding: 12px 18px 28px 18px;
            }
            .header-content-wrapper h1.name {
                font-size: 24px !important;
            }
            .header-titles .title-line {
                font-size: 15px !important;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="main-wrapper">
            <article class="resume-wrapper text-center position-relative">
                <div class="resume-wrapper-inner mx-auto text-left bg-white shadow-lg">
                    <header class="resume-header">
                        <div class="media flex-column flex-md-row">
                            <div class="header-photo-wrapper">
                                <img class="picture img-fluid" src="<?= $photo ?>" alt="<?= $name ?>">
                            </div>
                            <div class="media-body header-content-wrapper">
                                <h1 class="name"><?= $name ?></h1>
                                <div class="header-titles">
                                    <?php foreach ($titles as $title): ?>
                                        <div class="title-line"><?= htmlspecialchars($title) ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <ul class="header-contact-list">
                                    <?php if (!empty($emails)): ?>
                                        <li><i class="fa fa-envelope"></i> <?= $emails ?></li>
                                    <?php endif; ?>
                                    <?php if (!empty($phone)): ?>
                                        <li><i class="fa fa-telegram"></i> <?= $phone ?></li>
                                    <?php endif; ?>
                                </ul>
                                <?php if (!empty($gitUrl)): ?>
                                    <div>
                                        <a href="<?= $gitUrl ?>" target="_blank" class="github-pill-link">
                                            <i class="fa fa-github"></i>
                                            <span><?= $gitLabel ?></span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </header>
                    <div class="resume-body p-5">

                        <section class="resume-section summary-section mb-5">
                            <h2 class="resume-section-title text-uppercase font-weight-bold pb-3 mb-3">Perfil</h2>
                            <div class="resume-section-content text-justify">
                                <p><?= $profile ?></p>
                            </div>
                        </section>

                        <div class="row">
                            <div class="col-lg-9">

                                <section class="resume-section experience-section mb-3">
                                    <h2 class="resume-section-title text-uppercase font-weight-bold pb-3 mb-3">Experiencia laboral</h2>
                                    <div class="resume-section-content">
                                        <div class="resume-timeline position-relative">
                                            <?php foreach ($data['experience'] ?? [] as $exp): ?>
                                                <article class="resume-timeline-item position-relative pb-5">
                                                    <div class="resume-timeline-item-header mb-2">
                                                        <div class="d-flex flex-column flex-md-row">
                                                            <h3 class="resume-position-title font-weight-bold mb-1"><?= htmlspecialchars($exp['position'] ?? '') ?></h3>
                                                            <div class="resume-company-name ml-auto"><?= htmlspecialchars($exp['company'] ?? '') ?></div>
                                                        </div>
                                                        <div class="resume-position-time"><?= htmlspecialchars($exp['period'] ?? '') ?></div>
                                                    </div>
                                                    <div class="resume-timeline-item-desc">
                                                        <p><?= nl2br(htmlspecialchars($exp['description'] ?? '')) ?></p>
                                                        <?php if (!empty($exp['technologies'])): ?>
                                                            <h4 class="resume-timeline-item-desc-heading font-weight-bold">Technologies used:</h4>
                                                            <ul class="list-inline">
                                                                <?php foreach ($exp['technologies'] as $tech): ?>
                                                                    <li class="list-inline-item"><span class="badge badge-primary badge-pill"><?= htmlspecialchars($tech) ?></span></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>
                                                    </div>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <h2 class="resume-section-title text-uppercase font-weight-bold pb-3 mb-3 pt-3">EDUCACIÓN</h2>
                                    <div class="resume-section-content">
                                        <div class="resume-timeline position-relative">
                                            <?php foreach ($data['education'] ?? [] as $edu): ?>
                                                <article class="resume-timeline-item position-relative pb-5">
                                                    <div class="resume-timeline-item-header mb-2">
                                                        <div class="d-flex flex-column flex-md-row">
                                                            <h3 class="resume-position-title font-weight-bold mb-1"><?= htmlspecialchars($edu['degree'] ?? '') ?></h3>
                                                            <div class="resume-company-name ml-auto"><?= htmlspecialchars($edu['institution'] ?? '') ?></div>
                                                        </div>
                                                        <div class="resume-position-time"><?= htmlspecialchars($edu['period'] ?? '') ?></div>
                                                    </div>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <h2 class="resume-section-title text-uppercase font-weight-bold pb-3 mb-3 pt-3">REFERENCIAS PERSONALES</h2>
                                    <div class="resume-section-content">
                                        <div class="resume-timeline position-relative">
                                            <?php foreach ($data['references'] ?? [] as $ref): ?>
                                                <article class="resume-timeline-item position-relative pb-5">
                                                    <div class="resume-timeline-item-header mb-2">
                                                        <div class="d-flex flex-column flex-md-row">
                                                            <h3 class="resume-position-title font-weight-bold mb-1"><?= htmlspecialchars($ref['name'] ?? '') ?></h3>
                                                            <?php if (!empty($ref['company'])): ?>
                                                                <div class="resume-company-name ml-auto"><?= htmlspecialchars($ref['company']) ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="resume-position-time"><?= htmlspecialchars($ref['phone'] ?? '') ?></div>
                                                    </div>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <div class="col-lg-3">
                                <section class="resume-section skills-section mb-5">
                                    <h2 class="resume-section-title text-uppercase font-weight-bold pb-3 mb-3">Habilidades &amp; Herramientas</h2>
                                    <div class="resume-section-content">
                                        <?php foreach ($data['skills']['categories'] ?? [] as $cat): ?>
                                            <div class="resume-skill-item">
                                                <h4 class="resume-skills-cat font-weight-bold"><?= htmlspecialchars($cat['category'] ?? '') ?></h4>
                                                <ul class="list-unstyled mb-4">
                                                    <?php foreach ($cat['items'] ?? [] as $item): ?>
                                                        <li class="mb-2">
                                                            <div class="resume-skill-name"><?= htmlspecialchars($item['name'] ?? '') ?></div>
                                                            <div class="progress resume-progress">
                                                                <div class="progress-bar theme-progress-bar-dark" role="progressbar" style="width: <?= (int)($item['level'] ?? 80) ?>%" aria-valuenow="<?= (int)($item['level'] ?? 80) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                            </div>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if (!empty($data['skills']['others'])): ?>
                                            <div class="resume-skill-item">
                                                <h4 class="resume-skills-cat font-weight-bold">Others</h4>
                                                <ul class="list-inline">
                                                    <?php foreach ($data['skills']['others'] as $other): ?>
                                                        <li class="list-inline-item"><span class="badge badge-light"><?= htmlspecialchars($other) ?>,</span></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </section>

                                <section class="resume-section education-section mb-5">
                                    <h2 class="resume-section-title text-uppercase font-weight-bold pb-3 mb-3">Contacto Personal</h2>
                                    <div class="resume-section-content">
                                        <ul class="list-unstyled">
                                            <?php if (!empty($data['contact']['phone'])): ?>
                                                <li class="mb-2">
                                                    <div class="resume-degree font-weight-bold">Teléfono:</div>
                                                    <div class="resume-degree-org"><?= htmlspecialchars($data['contact']['phone']) ?></div>
                                                    <?php if (!empty($data['contact']['phone_note'])): ?>
                                                        <div class="resume-degree-time"><?= htmlspecialchars($data['contact']['phone_note']) ?></div>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endif; ?>
                                            <?php if (!empty($data['contact']['address'])): ?>
                                                <li class="mb-2">
                                                    <div class="resume-degree font-weight-bold">Dirección:</div>
                                                    <div class="resume-degree-org"><?= htmlspecialchars($data['contact']['address']) ?></div>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </section>

                                <section class="resume-section language-section mb-5">
                                    <h2 class="resume-section-title text-uppercase font-weight-bold pb-3 mb-3">Idiomas</h2>
                                    <div class="resume-section-content">
                                        <ul class="list-unstyled resume-lang-list">
                                            <?php foreach ($data['languages'] ?? [] as $lang): ?>
                                                <li class="mb-2">
                                                    <span class="resume-lang-name font-weight-bold"><?= htmlspecialchars($lang['language'] ?? '') ?></span>
                                                    <?php if (!empty($lang['level'])): ?>
                                                        <small class="text-muted font-weight-normal">(<?= htmlspecialchars($lang['level']) ?>)</small>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </section>

                                <section class="resume-section interests-section mb-5">
                                    <h2 class="resume-section-title text-uppercase font-weight-bold pb-3 mb-3">Intereses</h2>
                                    <div class="resume-section-content">
                                        <ul class="list-unstyled">
                                            <?php foreach ($data['interests'] ?? [] as $interest): ?>
                                                <li class="mb-1"><?= htmlspecialchars($interest) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </section>
                            </div>
                        </div>

                        <!-- Awards / Documents Section -->
                        <div class="row">
                            <div class="col-lg-7">
                                <?php if (!empty($data['certificates'])): ?>
                                    <section class="resume-section reference-section mb-3">
                                        <h2 class="resume-section-title text-uppercase font-weight-bold pb-3 mb-3">Certificados</h2>
                                        <div class="resume-section-content">
                                            <ul class="list-unstyled resume-awards-list">
                                                <?php foreach ($data['certificates'] as $item): ?>
                                                    <li class="mb-2 pl-4 position-relative">
                                                        <i class="resume-award-icon fa-newspaper fas position-absolute" data-fa-transform="shrink-2"></i>
                                                        <div class="resume-award-name">
                                                            <?php if (!empty($item['file'])): ?>
                                                                <a href="<?= htmlspecialchars($item['file']) ?>"><?= htmlspecialchars($item['title']) ?></a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($item['title']) ?>
                                                            <?php endif; ?>
                                                            <?= !empty($item['detail']) ? (', ' . htmlspecialchars($item['detail'])) : '' ?>
                                                        </div>
                                                        <div class="resume-award-desc"><?= htmlspecialchars($item['description'] ?? '') ?></div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </section>
                                <?php endif; ?>

                                <?php if (!empty($data['diplomados'])): ?>
                                    <section class="resume-section reference-section mb-3">
                                        <h2 class="resume-section-title text-uppercase font-weight-bold pb-3 mb-3">Diplomados</h2>
                                        <div class="resume-section-content">
                                            <ul class="list-unstyled resume-awards-list">
                                                <?php foreach ($data['diplomados'] as $item): ?>
                                                    <li class="mb-2 pl-4 position-relative">
                                                        <i class="resume-award-icon fa-newspaper fas position-absolute" data-fa-transform="shrink-2"></i>
                                                        <div class="resume-award-name">
                                                            <?php if (!empty($item['file'])): ?>
                                                                <a href="<?= htmlspecialchars($item['file']) ?>"><?= htmlspecialchars($item['title']) ?></a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($item['title']) ?>
                                                            <?php endif; ?>
                                                            <?= !empty($item['detail']) ? (', ' . htmlspecialchars($item['detail'])) : '' ?>
                                                        </div>
                                                        <div class="resume-award-desc"><?= htmlspecialchars($item['description'] ?? '') ?></div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </section>
                                <?php endif; ?>

                                <?php if (!empty($data['diplomas'])): ?>
                                    <section class="resume-section reference-section mb-3">
                                        <h2 class="resume-section-title text-uppercase font-weight-bold pb-3 mb-3">Diplomas</h2>
                                        <div class="resume-section-content">
                                            <ul class="list-unstyled resume-awards-list">
                                                <?php foreach ($data['diplomas'] as $item): ?>
                                                    <li class="mb-2 pl-4 position-relative">
                                                        <i class="resume-award-icon fa-newspaper fas position-absolute" data-fa-transform="shrink-2"></i>
                                                        <div class="resume-award-name">
                                                            <?php if (!empty($item['file'])): ?>
                                                                <a href="<?= htmlspecialchars($item['file']) ?>"><?= htmlspecialchars($item['title']) ?></a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($item['title']) ?>
                                                            <?php endif; ?>
                                                            <?= !empty($item['detail']) ? (', ' . htmlspecialchars($item['detail'])) : '' ?>
                                                        </div>
                                                        <div class="resume-award-desc"><?= htmlspecialchars($item['description'] ?? '') ?></div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </section>
                                <?php endif; ?>

                                <?php if (!empty($data['workshops'])): ?>
                                    <section class="resume-section reference-section mb-3">
                                        <h2 class="resume-section-title text-uppercase font-weight-bold pb-3 mb-3">Talleres</h2>
                                        <div class="resume-section-content">
                                            <ul class="list-unstyled resume-awards-list">
                                                <?php foreach ($data['workshops'] as $item): ?>
                                                    <li class="mb-2 pl-4 position-relative">
                                                        <i class="resume-award-icon fa-newspaper fas position-absolute" data-fa-transform="shrink-2"></i>
                                                        <div class="resume-award-name">
                                                            <?php if (!empty($item['file'])): ?>
                                                                <a href="<?= htmlspecialchars($item['file']) ?>"><?= htmlspecialchars($item['title']) ?></a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($item['title']) ?>
                                                            <?php endif; ?>
                                                            <?= !empty($item['detail']) ? (', ' . htmlspecialchars($item['detail'])) : '' ?>
                                                        </div>
                                                        <div class="resume-award-desc"><?= htmlspecialchars($item['description'] ?? '') ?></div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </section>
                                <?php endif; ?>
                            </div>

                            <div class="col-lg-5">
                                <?php if (!empty($data['recognitions'])): ?>
                                    <section class="resume-section reference-section mb-3">
                                        <h2 class="resume-section-title text-uppercase font-weight-bold pb-3 mb-3">Reconocimiento</h2>
                                        <div class="resume-section-content">
                                            <ul class="list-unstyled resume-awards-list">
                                                <?php foreach ($data['recognitions'] as $item): ?>
                                                    <li class="mb-2 pl-4 position-relative">
                                                        <i class="resume-award-icon fas fa-trophy position-absolute" data-fa-transform="shrink-2"></i>
                                                        <div class="resume-award-name"><?= htmlspecialchars($item['title']) ?></div>
                                                        <div class="resume-award-desc"><?= htmlspecialchars($item['description'] ?: ($item['detail'] ?? '')) ?></div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </section>
                                <?php endif; ?>

                                <?php if (!empty($data['university_titles'])): ?>
                                    <section class="resume-section reference-section mb-3">
                                        <h2 class="resume-section-title text-uppercase font-weight-bold pb-3 mb-3">Titulo</h2>
                                        <div class="resume-section-content">
                                            <ul class="list-unstyled resume-awards-list">
                                                <?php foreach ($data['university_titles'] as $item): ?>
                                                    <li class="mb-2 pl-4 position-relative">
                                                        <i class="resume-award-icon fa-newspaper fas position-absolute" data-fa-transform="shrink-2"></i>
                                                        <div class="resume-award-name">
                                                            <?php if (!empty($item['file'])): ?>
                                                                <a href="<?= htmlspecialchars($item['file']) ?>"><?= htmlspecialchars($item['title']) ?></a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($item['title']) ?>
                                                            <?php endif; ?>
                                                            <?= !empty($item['detail']) ? (', ' . htmlspecialchars($item['detail'])) : '' ?>
                                                        </div>
                                                        <div class="resume-award-desc"><?= htmlspecialchars($item['description'] ?? '') ?></div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </section>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                    <!--//resume-body-->
                </div>
            </article>
        </div>
    </div>
    <script>
        // Botón discreto para acceder al panel de administración solo cuando se corre en localhost
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' || window.location.protocol === 'file:') {
            const btn = document.createElement('a');
            btn.href = 'admin.php';
            btn.title = 'Abrir Panel de Administración del CV';
            btn.innerHTML = '<i class="fa fa-sliders" style="margin-right:8px;"></i> Administrar CV';
            btn.style.cssText = 'position:fixed;bottom:22px;right:22px;background:linear-gradient(135deg, #2563eb, #1d4ed8);color:#fff;padding:11px 18px;border-radius:50px;box-shadow:0 6px 20px rgba(37,99,235,0.4);text-decoration:none;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;font-size:13.5px;font-weight:600;z-index:99999;display:flex;align-items:center;transition:all 0.25s ease;letter-spacing:0.2px;';
            btn.onmouseover = () => { btn.style.transform = 'translateY(-2px)'; btn.style.boxShadow = '0 8px 25px rgba(37,99,235,0.5)'; };
            btn.onmouseout = () => { btn.style.transform = 'translateY(0)'; btn.style.boxShadow = '0 6px 20px rgba(37,99,235,0.4)'; };
            document.body.appendChild(btn);
        }
    </script>
</body>
</html>
<?php
    return ob_get_clean();
}
