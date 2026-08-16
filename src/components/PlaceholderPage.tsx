import Link from "next/link";

export default function PlaceholderPage({
  title,
  description,
}: {
  title: string;
  description: string;
}) {
  return (
    <section className="section" style={{ paddingTop: "8rem" }}>
      <div className="container">
        <div className="section-title">
          <h2>{title}</h2>
          <p>{description}</p>
        </div>
        <Link href="/" className="btn btn-primary btn-sm rounded-pill px-3">
          Voltar ao início
        </Link>
      </div>
    </section>
  );
}
