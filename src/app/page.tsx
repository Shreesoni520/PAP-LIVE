import Link from "next/link";
import { getDb, type RowDataPacket } from "@/lib/db";
import { getPublicSession } from "@/lib/session";

type NewsRow = RowDataPacket & {
  id: number;
  titulo: string;
  resumo: string | null;
  imagem_lista: string | null;
  data_publicacao: string | Date | null;
};

async function getLatestNews(): Promise<NewsRow[]> {
  try {
    const db = getDb();
    const [rows] = await db.query<NewsRow[]>(
      `SELECT id, titulo, resumo, imagem_lista, data_publicacao
       FROM noticias
       ORDER BY data_publicacao DESC, id DESC
       LIMIT 3`,
    );
    return rows;
  } catch {
    return [];
  }
}

function formatDate(value: string | Date | null): string {
  if (!value) return "";
  const d = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleDateString("pt-PT");
}

function trimResumo(text: string | null): string {
  const value = text || "";
  return value.length > 120 ? `${value.slice(0, 117)}...` : value;
}

export default async function HomePage() {
  const news = await getLatestNews();
  const session = await getPublicSession();
  const loggedIn = Boolean(session.publicUserId);

  return (
    <>
      <section id="hero" className="hero section dark-background">
        <div className="container">
          <div className="row gy-4">
            <div
              className="col-lg-6 order-2 order-lg-1 d-flex flex-column justify-content-center"
              data-aos="zoom-out"
            >
              <h1>Bem-vindo à plataforma de Évora</h1>
              <p>
                Consulte informação útil, notícias e ocorrências da cidade de
                Évora, tudo num só sítio simples e rápido de usar.
              </p>
              <div className="d-flex gap-2 flex-wrap">
                <Link href="/information" className="btn-get-started">
                  Informação
                </Link>
                <Link
                  href="/contact"
                  className="btn btn-outline-light btn-sm d-flex align-items-center gap-1"
                >
                  Contacto
                </Link>
              </div>
            </div>

            <div
              className="col-lg-6 order-1 order-lg-2 hero-img"
              data-aos="zoom-out"
              data-aos-delay="200"
            >
              <div className="hero-image-card">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src="/assets/img/hero-img.png"
                  className="img-fluid protected-img"
                  alt="Vista da cidade"
                  draggable={false}
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      <section id="about" className="about section">
        <div className="container">
          <div className="row gy-4 align-items-center">
            <div className="col-lg-6" data-aos="fade-up" data-aos-delay="100">
              <h2>Informação sobre Évora</h2>
              <p className="mb-3">
                Esta plataforma foi criada para ajudar residentes e visitantes a
                encontrarem rapidamente informação sobre a cidade de Évora,
                desde notícias a ocorrências importantes.
              </p>
              <ul className="list-unstyled">
                <li className="mb-2">
                  <i className="bi bi-check2-circle text-primary me-1" />
                  Acesso simples a notícias relevantes.
                </li>
                <li className="mb-2">
                  <i className="bi bi-check2-circle text-primary me-1" />
                  Consulta de ocorrências e pontos de interesse no mapa.
                </li>
                <li className="mb-2">
                  <i className="bi bi-check2-circle text-primary me-1" />
                  Área reservada para gerir o seu perfil e dados.
                </li>
              </ul>
            </div>

            <div className="col-lg-6" data-aos="fade-up" data-aos-delay="200">
              <div className="ratio ratio-16x9 rounded-3 overflow-hidden shadow">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src="/assets/img/evora.jpeg"
                  className="w-100 h-100 protected-img"
                  style={{ objectFit: "cover" }}
                  alt="Vista da cidade de Évora"
                  draggable={false}
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="section light-background">
        <div className="container">
          <div className="row gy-4 align-items-center">
            <div className="col-lg-6" data-aos="fade-up" data-aos-delay="100">
              <h2>Mapa de ocorrências</h2>
              <p className="mb-3">
                Veja no mapa os principais pontos e ocorrências registadas na
                cidade.
              </p>
              <ul className="list-unstyled mb-4">
                <li className="mb-2">
                  <i className="bi bi-geo-alt-fill text-danger me-1" />
                  Localização de ocorrências importantes.
                </li>
                <li className="mb-2">
                  <i className="bi bi-exclamation-triangle-fill text-warning me-1" />
                  Informação rápida sobre situações a ter em atenção.
                </li>
              </ul>
              <Link
                href="/mapa"
                className="btn btn-primary btn-sm rounded-pill px-3"
              >
                Abrir mapa
              </Link>
              <Link
                href="/listar-ocorrencias"
                className="btn btn-outline-primary btn-sm rounded-pill px-3 ms-1"
              >
                Ver lista de ocorrências
              </Link>
            </div>

            <div className="col-lg-6" data-aos="fade-up" data-aos-delay="200">
              <div className="ratio ratio-16x9 rounded-3 overflow-hidden shadow-sm">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src="/assets/img/steps/evora.png"
                  className="w-100 h-100 protected-img"
                  style={{ objectFit: "cover" }}
                  alt="Mapa de ocorrências em Évora"
                  draggable={false}
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      <section id="noticias-destaque" className="section">
        <div className="container">
          <div
            className="section-title text-center mb-4"
            data-aos="fade-up"
          >
            <h2>Notícias em destaque</h2>
            <p>As últimas notícias sobre Évora.</p>
          </div>

          <div className="row gy-4">
            {news.length > 0 ? (
              news.map((noticia, index) => (
                <div
                  key={noticia.id}
                  className="col-md-4"
                  data-aos="fade-up"
                  data-aos-delay={100 + index * 50}
                >
                  <div className="card card-news h-100">
                    <div className="news-card-img-wrapper">
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img
                        src={
                          noticia.imagem_lista ||
                          "/assets/img/blog/blog-post-1.webp"
                        }
                        alt="Notícia"
                      />
                    </div>
                    <div className="card-body d-flex flex-column">
                      <small className="text-muted d-block mb-1">
                        {formatDate(noticia.data_publicacao)}
                      </small>
                      <h5 className="card-title">{noticia.titulo}</h5>
                      <p className="card-text small text-muted flex-grow-1">
                        {trimResumo(noticia.resumo)}
                      </p>
                      <Link
                        href={`/noticias/${noticia.id}`}
                        className="stretched-link small"
                      >
                        Ler notícia
                      </Link>
                    </div>
                  </div>
                </div>
              ))
            ) : (
              <div className="col-12">
                <p className="text-center text-muted">
                  Ainda não existem notícias publicadas.
                </p>
              </div>
            )}
          </div>

          <div
            className="text-center mt-3"
            data-aos="fade-up"
            data-aos-delay="250"
          >
            <Link
              href="/noticias"
              className="btn btn-outline-primary btn-sm rounded-pill px-3"
            >
              Ver todas as notícias
            </Link>
          </div>
        </div>
      </section>

      <section className="call-to-action section dark-background">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src="/assets/img/bg/bg-8.webp" alt="" className="img-fluid" />
        <div className="container">
          <div className="row" data-aos="zoom-in" data-aos-delay="100">
            <div className="col-xl-9 text-center text-xl-start">
              {!loggedIn ? (
                <>
                  <h3>Crie a sua conta</h3>
                  <p>
                    Com uma conta pode aceder à área reservada, gerir o seu
                    perfil e ter uma experiência mais personalizada na
                    plataforma.
                  </p>
                </>
              ) : (
                <>
                  <h3>Bem-vindo de volta</h3>
                  <p>
                    Aceda ao seu perfil para consultar e atualizar os seus
                    dados, gerir as suas preferências e acompanhar a sua
                    atividade na plataforma.
                  </p>
                </>
              )}
            </div>
            <div className="col-xl-3 cta-btn-container text-center">
              {!loggedIn ? (
                <Link className="cta-btn align-middle" href="/signup">
                  Criar conta
                </Link>
              ) : (
                <Link className="cta-btn align-middle" href="/profile">
                  Ver perfil
                </Link>
              )}
            </div>
          </div>
        </div>
      </section>

      
    </>
  );
}
