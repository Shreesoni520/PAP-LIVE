import Link from "next/link";

export default function InformationPage() {
  return (
    <>
      <section
        id="hero"
        className="hero section dark-background"
        style={{
          height: 300,
          padding: 0,
          overflow: "hidden",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          textAlign: "center",
          marginBottom: 40,
          paddingTop: "5rem",
        }}
      >
        <div className="container" data-aos="fade-up">
          <h1>Informação útil e funcionalidades</h1>
          <p>Tudo o que precisa de saber para usar o Reporta Évora.</p>
        </div>
      </section>

      <section id="info-util" className="section">
        <div className="container">
          <div
            className="mb-4 p-4"
            style={{
              borderRadius: 18,
              border: "1px solid #e5e7eb",
              background:
                "radial-gradient(circle at top left, #0d6efd15, transparent 55%), radial-gradient(circle at bottom right, #22c55e15, transparent 55%), #ffffff",
              boxShadow: "0 14px 40px rgba(15, 23, 42, 0.12)",
            }}
          >
            <p className="mb-2" style={{ color: "#4b5563", fontWeight: 600 }}>
              PARA O CIDADÃO
            </p>
            <p style={{ color: "#6b7280" }}>
              Esta plataforma permite consultar notícias, ver o mapa de
              ocorrências, reportar problemas no espaço público e nas estradas,
              e acompanhar os seus pedidos através da área pessoal.
            </p>
            <div className="row gy-3 mt-2">
              <div className="col-md-4">
                <div className="p-3 h-100 border rounded-3 bg-white">
                  <h5>Mapa</h5>
                  <p className="text-muted small mb-2">
                    Consulte árvores e ocorrências no mapa interativo.
                  </p>
                  <Link href="/mapa">Abrir mapa</Link>
                </div>
              </div>
              <div className="col-md-4">
                <div className="p-3 h-100 border rounded-3 bg-white">
                  <h5>Reportar</h5>
                  <p className="text-muted small mb-2">
                    Registe ocorrências urbanas ou de estrada com foto e local.
                  </p>
                  <Link href="/ocorrencias">Registar ocorrência</Link>
                </div>
              </div>
              <div className="col-md-4">
                <div className="p-3 h-100 border rounded-3 bg-white">
                  <h5>Conta</h5>
                  <p className="text-muted small mb-2">
                    Crie conta para perfil, segurança e acompanhamento.
                  </p>
                  <Link href="/signup">Criar conta</Link>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
