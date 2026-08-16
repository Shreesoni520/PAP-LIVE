import Link from "next/link";
import { getDb, type RowDataPacket } from "@/lib/db";

type NewsRow = RowDataPacket & {
  id: number;
  titulo: string;
  resumo: string | null;
  imagem_lista: string | null;
};

async function getLatestNews(): Promise<NewsRow[]> {
  try {
    const db = getDb();
    const [rows] = await db.query<NewsRow[]>(
      `SELECT id, titulo, resumo, imagem_lista
       FROM noticias
       ORDER BY data_publicacao DESC, id DESC
       LIMIT 3`,
    );
    return rows;
  } catch {
    return [];
  }
}

export default async function HomePage() {
  const news = await getLatestNews();

  return (
    <>
      <section className="hero">
        <div className="hero-inner">
          <p className="hero-kicker">Cidade de Évora</p>
          <h1>Reporta Évora</h1>
          <p>
            Reporte problemas no espaço público e nas estradas, acompanhe o
            estado das ocorrências e mantenha-se informado sobre a cidade.
          </p>
          <div className="hero-actions">
            <Link href="/ocorrencias" className="btn-primary">
              Reportar ocorrência
            </Link>
            <Link href="/mapa" className="btn-ghost">
              Ver mapa
            </Link>
          </div>
        </div>
      </section>

      <section className="section">
        <h2 className="section-title">Como funciona</h2>
        <p className="section-lead">
          Um canal direto entre cidadãos e os serviços municipais.
        </p>
        <div className="feature-grid">
          <article className="feature-item">
            <h3>1. Registe-se</h3>
            <p>Crie a sua conta para reportar e acompanhar pedidos.</p>
          </article>
          <article className="feature-item">
            <h3>2. Reporte</h3>
            <p>Indique o local, descreva o problema e anexe uma foto.</p>
          </article>
          <article className="feature-item">
            <h3>3. Acompanhe</h3>
            <p>A equipa municipal trata a ocorrência e atualiza o estado.</p>
          </article>
        </div>
      </section>

      <section className="section">
        <h2 className="section-title">Notícias recentes</h2>
        <p className="section-lead">
          Atualizações e avisos publicados pela equipa.
        </p>
        {news.length === 0 ? (
          <p className="section-lead">
            Ainda não há notícias para mostrar, ou a base de dados ainda não
            está ligada neste ambiente.
          </p>
        ) : (
          <div className="news-grid">
            {news.map((item) => (
              <Link
                key={item.id}
                href={`/noticias/${item.id}`}
                className="news-card"
              >
                <div className="news-card-img" />
                <div className="news-card-body">
                  <h3>{item.titulo}</h3>
                  <p>{item.resumo || "Ler notícia completa"}</p>
                </div>
              </Link>
            ))}
          </div>
        )}
      </section>
    </>
  );
}
