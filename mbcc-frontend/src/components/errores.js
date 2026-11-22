// Primer mensaje de error de un campo, según la respuesta 422 de Laravel: { errors: { campo: ['…'] } }
export const errorDe = (err, campo) => err?.errors?.[campo]?.[0]
