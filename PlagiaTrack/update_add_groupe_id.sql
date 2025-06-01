ALTER TABLE documents ADD COLUMN groupe_id INT DEFAULT NULL;

ALTER TABLE documents
ADD CONSTRAINT fk_groupe_document FOREIGN KEY (groupe_id) REFERENCES groupes_documents(id) ON DELETE SET NULL;
