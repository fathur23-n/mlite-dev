<?php

namespace Plugins\Satu_Sehat;

use SatuSehat\Src\CarePlan;
use Systems\AdminModule;
use SatuSehat\Src\ClinicalImpression;
use SatuSehat\Src\Composition;
use SatuSehat\Src\Condition;
use SatuSehat\Src\DiagnosticReport;
use SatuSehat\Src\Respiratory;
use SatuSehat\Src\Observation;
use SatuSehat\Src\Medication;
use SatuSehat\Src\MedicationDispense;
use SatuSehat\Src\MedicationRequest;
use SatuSehat\Src\Procedure;
use SatuSehat\Src\QuestionareMedication;
use SatuSehat\Src\ServiceRequest;
use SatuSehat\Src\Temperature;
use SatuSehat\Src\Specimen;

class Admin extends AdminModule
{

  private $authurl;
  private $fhirurl;
  private $clientid;
  private $secretkey;
  private $organizationid;

  public function init()
  {
    $this->authurl = $this->settings->get('satu_sehat.authurl');
    $this->fhirurl = $this->settings->get('satu_sehat.fhirurl');
    $this->clientid = $this->settings->get('satu_sehat.clientid');
    $this->secretkey = $this->settings->get('satu_sehat.secretkey');
    $this->organizationid = $this->settings->get('satu_sehat.organizationid');
  }

  public function getObatByCode($code)
  {
    if ($this->getAccessToken() === '') {
      return ['error' => 'Gagal mendapatkan access token'];
    }

    $url = $this->settings->get('satu_sehat.authurl');
    $parsed = parse_url($url);
    $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];

    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $baseUrl . '/kfa-v2/products?identifier=kfa&code=' . urlencode($code),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $this->getAccessToken(),
        'Accept: application/json'
      ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
  }

  public function navigation()
  {
    return [
      'Kelola' => 'manage',
      'Referensi Praktisi' => 'praktisi',
      'Referensi Pasien' => 'pasien',
      'Mapping Departemen' => 'departemen',
      'Mapping Lokasi' => 'lokasi',
      'Mapping Praktisi' => 'mappingpraktisi',
      'Mapping Obat' => 'mappingobat',
      'Mapping Laboratorium' => 'mappinglab',
      'Mapping Radiologi' => 'mappingrad',
      'Data Response' => 'response',
      'Mini PACS'          => 'minipacs', 
      'Mapping Vaksin' => 'mappingvaksin',
      'Verifikasi KYC' => 'kyc',
      'Pengaturan' => 'settings',
    ];
  }

  public function getManage()
  {
    $sub_modules = [
      ['name' => 'Referensi Praktisi', 'url' => url([ADMIN, 'satu_sehat', 'praktisi']), 'icon' => 'heart', 'desc' => 'Referensi praktisi satu sehat'],
      ['name' => 'Referensi Pasien', 'url' => url([ADMIN, 'satu_sehat', 'pasien']), 'icon' => 'heart', 'desc' => 'Referensi pasien satu sehat'],
      ['name' => 'Mapping Departemen', 'url' => url([ADMIN, 'satu_sehat', 'departemen']), 'icon' => 'heart', 'desc' => 'Mapping departemen satu sehat'],
      ['name' => 'Mapping Lokasi', 'url' => url([ADMIN, 'satu_sehat', 'lokasi']), 'icon' => 'heart', 'desc' => 'Mapping lokasi satu sehat'],
      ['name' => 'Mapping Praktisi', 'url' => url([ADMIN, 'satu_sehat', 'mappingpraktisi']), 'icon' => 'heart', 'desc' => 'Mapping praktisi satu sehat'],
      ['name' => 'Mapping Obat', 'url' => url([ADMIN, 'satu_sehat', 'mappingobat']), 'icon' => 'heart', 'desc' => 'Mapping obat satu sehat'],
      ['name' => 'Mapping Laboratorium', 'url' => url([ADMIN, 'satu_sehat', 'mappinglab']), 'icon' => 'heart', 'desc' => 'Mapping laboratorium satu sehat'],
      ['name' => 'Mapping Radiologi', 'url' => url([ADMIN, 'satu_sehat', 'mappingrad']), 'icon' => 'heart', 'desc' => 'Mapping radiologi satu sehat'],
      ['name' => 'Data Response', 'url' => url([ADMIN, 'satu_sehat', 'response']), 'icon' => 'heart', 'desc' => 'Data encounter satu sehat'],
      ['name' => 'Mini PACS',            'url' => url([ADMIN, 'satu_sehat', 'minipacs']),       'icon' => 'film',  'desc' => 'Konversi & kelola gambar radiologi DICOM'],
      ['name' => 'Mapping Vaksin', 'url' => url([ADMIN, 'satu_sehat', 'mappingvaksin']), 'icon' => 'medkit', 'desc' => 'Mapping vaksin Satu Sehat'],
      ['name' => 'Verifikasi KYC', 'url' => url([ADMIN, 'satu_sehat', 'kyc']), 'icon' => 'heart', 'desc' => 'Verifikasi KYC satu sehat'],
      ['name' => 'Pengaturan', 'url' => url([ADMIN, 'satu_sehat', 'settings']), 'icon' => 'heart', 'desc' => 'Pengaturan satu sehat'],
    ];
    return $this->draw('manage.html', ['sub_modules' => $sub_modules]);
  }

  private function generateDicomUid(): string
  {
    return '2.25.' . str_replace(['.', ' '], '', microtime()) . mt_rand(100000, 999999);
  }
 
  private function getDefaultStudyDescription(string $modality): string
  {
    return [
        'CR' => 'X-Ray',        'CT' => 'CT Scan',   'MR' => 'MRI',
        'US' => 'USG',          'DX' => 'Digital X-Ray', 'MG' => 'Mammography',
        'PX' => 'Panoramic X-Ray', 'IO' => 'Intra-oral Radiography',
        'NM' => 'Nuclear Medicine', 'XA' => 'X-Ray Angiography', 'RF' => 'Fluoroscopy',
    ][$modality] ?? $modality;
  }
 
private function getOrCreateStudy(string $no_rawat, string $modality, string $description, array $permintaan): array
{
    $row = $this->db('mlite_mini_pacs_study')
        ->where('no_rawat', $no_rawat)->where('modality', $modality)->oneArray();
    if (!$row) {
        $this->db('mlite_mini_pacs_study')->save([
            'no_rawat'           => $no_rawat,
            'study_instance_uid' => $this->generateDicomUid(),
            'study_date'         => ($permintaan['tgl_permintaan'] ?? date('Y-m-d')) . ' ' . ($permintaan['jam_permintaan'] ?? '00:00:00'),
            'modality'           => $modality,
            'description'        => $description,
        ]);
        $row = $this->db('mlite_mini_pacs_study')
            ->where('no_rawat', $no_rawat)->where('modality', $modality)->oneArray();
    }
    return $row;
  }
 
  private function getOrCreateSeries(int $study_id, string $no_rawat, string $modality): array
  {
    $row = $this->db('mlite_mini_pacs_series')->where('study_id', $study_id)->oneArray();
    if (!$row) {
        $this->db('mlite_mini_pacs_series')->save([
            'study_id'            => $study_id,
            'no_rawat'            => $no_rawat,
            'series_instance_uid' => $this->generateDicomUid(),
            'modality'            => $modality,
            'series_number'       => 1,
        ]);
        $row = $this->db('mlite_mini_pacs_series')->where('study_id', $study_id)->oneArray();
    }
    return $row;
  }
 
  private function convertImageToDicom(
    string $src, string $tmp, string $out,
    string $study_uid, string $series_uid, string $sop_uid, string $sop_class,
    string $patient_name, string $patient_id, string $birth_date, string $sex,
    string $study_date, string $study_time,
    string $accession, string $modality, string $description, int $instance_no,
    string $nm_dokter = '', string $instansi = ''
    ): array {
    $cmd1 = sprintf(
        'img2dcm %s %s -k %s -k %s -k %s -k %s -k %s -k %s -k %s -k %s -k %s -k %s -k %s -k %s -k %s -k %s -k %s -k %s 2>&1',
        escapeshellarg($src), escapeshellarg($tmp),
        escapeshellarg('StudyInstanceUID='  . $study_uid),
        escapeshellarg('SeriesInstanceUID=' . $series_uid),
        escapeshellarg('SOPInstanceUID='    . $sop_uid),
        escapeshellarg('SOPClassUID='       . $sop_class),
        escapeshellarg('PatientName='       . $patient_name),
        escapeshellarg('PatientID='         . $patient_id),
        escapeshellarg('PatientBirthDate='  . $birth_date),
        escapeshellarg('PatientSex='        . $sex),
        escapeshellarg('StudyDate='         . $study_date),
        escapeshellarg('StudyTime='         . $study_time),
        escapeshellarg('AccessionNumber='   . $accession),
        escapeshellarg('Modality='          . $modality),
        escapeshellarg('StudyDescription='  . $description),
        escapeshellarg('InstanceNumber='    . $instance_no),
        escapeshellarg('InstitutionName='   . $instansi),
        escapeshellarg('PatientComments='   . $nm_dokter)
    );
    $o1 = []; $r1 = 0;
    exec($cmd1, $o1, $r1);
    if ($r1 !== 0 || !file_exists($tmp)) {
        return ['success' => false, 'message' => 'img2dcm gagal (code ' . $r1 . '): ' . implode(' | ', $o1)];
    }
    $cmd2 = sprintf('dcmdjpeg %s %s 2>&1', escapeshellarg($tmp), escapeshellarg($out));
    $o2 = []; $r2 = 0;
    exec($cmd2, $o2, $r2);
    if (file_exists($tmp)) unlink($tmp);
    if ($r2 !== 0 || !file_exists($out)) {
        return ['success' => false, 'message' => 'dcmdjpeg gagal (code ' . $r2 . '): ' . implode(' | ', $o2)];
    }
    return ['success' => true];
  }
 
  private function getDicomMeta(string $no_rawat): array
  {
    $rm  = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
    $nm  = $this->core->getPasienInfo('nm_pasien', $rm);
    $tgl = $this->core->getPasienInfo('tgl_lahir', $rm);
    $jk  = $this->core->getPasienInfo('jk', $rm);
    $sex = $jk === 'L' ? 'M' : ($jk === 'P' ? 'F' : 'O');
    return [
        'no_rkm_medis'    => $rm,
        'nm_pasien'       => $nm,
        'tgl_lahir'       => $tgl,
        'jk'              => $jk,
        'nm_pasien_dicom' => strtoupper(str_replace([' ', ','], ['^', ''], $nm)),
        'tgl_lahir_dicom' => str_replace('-', '', $tgl ?? ''),
        'sex_dicom'       => $sex,
    ];
  }
 
  public function getMiniPacs($no_rawat_converted = '')
  {
    $this->_addHeaderFiles();
    $no_rawat    = $no_rawat_converted ? revertNoRawat($no_rawat_converted) : '';
    $gambar_list = [];
    $permintaan  = [];
    $studies     = [];
    $ss_response = [];
    $instansi = $this->settings->get('settings.nama_instansi') ?: '';
 
    if ($no_rawat) {
        $gambar_list = $this->db('gambar_radiologi')->where('no_rawat', $no_rawat)->toArray();
        $permintaan  = $this->db('permintaan_radiologi')->where('no_rawat', $no_rawat)->oneArray();
        $meta        = $this->getDicomMeta($no_rawat);
        $ss_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();
 
        $noorder = $permintaan['noorder'] ?? '';
 
        foreach ($gambar_list as &$gambar) {
            if ($noorder) {
                $hash     = md5($gambar['lokasi_gambar']);
                $dcm_path = realpath(BASE_DIR) . '/uploads/pacs/' . $noorder . '/' . $noorder . '_' . $hash . '.dcm';
                $gambar['sudah_convert'] = file_exists($dcm_path) ? '1' : '0';
            } else {
                $gambar['sudah_convert'] = '0';
            }
        }
        unset($gambar);
 
        foreach ($this->db('mlite_mini_pacs_study')->where('no_rawat', $no_rawat)->toArray() as $study) {
            $series_list = $this->db('mlite_mini_pacs_series')->where('study_id', $study['id'])->toArray();
            $total_inst  = 0;
            foreach ($series_list as $s) {
                $total_inst += $this->db('mlite_mini_pacs_instance')->where('series_id', $s['id'])->count();
            }
            $studies[] = [
                'id'                 => $study['id'],
                'study_date'         => $study['study_date'],
                'modality'           => $study['modality'],
                'description'        => $study['description'],
                'study_instance_uid' => $study['study_instance_uid'],
                'series_count'       => count($series_list),
                'instance_count'     => $total_inst,
                'nm_pasien'          => $meta['nm_pasien'],
                'tgl_lahir'          => $meta['tgl_lahir'],
                'jk'                 => $meta['jk'],
                'no_rkm_medis'       => $meta['no_rkm_medis'],
                'noorder'            => $noorder,
            ];
        }
    }
 
    return $this->draw('mini.pacs.html', [
        'no_rawat'           => $no_rawat,
        'no_rawat_converted' => $no_rawat_converted,
        'gambar_list'        => $gambar_list,
        'permintaan'         => $permintaan,
        'studies'            => $studies,
        'ss_response'        => $ss_response,
        'instansi'           => $instansi,
        'webapps_url'        => WEBAPPS_URL,
    ]);
  }
 
  public function getSeriesDetail($study_id = '')
  {
    header('Content-Type: application/json');
    if (!$study_id) { echo json_encode(['success' => false, 'html' => '']); exit(); }
 
    $study       = $this->db('mlite_mini_pacs_study')->where('id', $study_id)->oneArray();
    $series_list = $this->db('mlite_mini_pacs_series')->where('study_id', $study_id)->toArray();
    $no_rawat    = $study['no_rawat'] ?? '';
    $meta        = $this->getDicomMeta($no_rawat);
 
    $meta_arr = [
        'name'     => $meta['nm_pasien'],
        'id'       => $meta['no_rkm_medis'],
        'birth'    => $meta['tgl_lahir'],
        'sex'      => $meta['jk'],
        'date'     => $study['study_date'] ?? '',
        'time'     => isset($study['study_date']) ? date('H:i:s', strtotime($study['study_date'])) : '-',
        'modality' => $study['modality'] ?? '',
    ];
 
    $html  = '<table class="table table-bordered" style="font-size:11px;margin:0;">';
    $html .= '<thead><tr style="background:#f5f7fa;">';
    $html .= '<th style="padding:7px 10px;">Series #</th>';
    $html .= '<th style="padding:7px 10px;">Modality</th>';
    $html .= '<th style="padding:7px 10px;"># Inst</th>';
    $html .= '<th style="padding:7px 10px;">File DCM</th>';
    $html .= '<th style="padding:7px 10px;">Aksi</th>';
    $html .= '</tr></thead><tbody>';
 
    $all_instances = [];
 
    foreach ($series_list as $series) {
        $instances = $this->db('mlite_mini_pacs_instance')->where('series_id', $series['id'])->toArray();
 
        $file_html = '';
        $aksi_html = '';
 
        foreach ($instances as $inst) {
            $fp     = realpath(BASE_DIR) . '/' . $inst['file_path'];
            $exists = file_exists($fp);
            $size   = $exists ? round(filesize($fp) / 1024, 1) . ' KB' : '-';
            $fname  = basename($inst['file_path']);
            $furl   = url($inst['file_path']);
            $purl   = url(ADMIN . '/satu_sehat/dcmpreview/' . $inst['id']);
 
            $badge = $exists
                ? '<span style="background:#27ae60;color:#fff;font-size:9px;padding:1px 5px;border-radius:3px;">' . $size . '</span>'
                : '<span style="background:#e74c3c;color:#fff;font-size:9px;padding:1px 5px;border-radius:3px;">Tidak Ada</span>';
 
            $file_html .= '<div style="margin-bottom:3px;display:flex;align-items:center;gap:5px;">'
                . '<span style="font-family:monospace;font-size:10px;color:#4a6fa5;">' . htmlspecialchars($fname) . '</span>'
                . $badge . '</div>';
 
            if ($exists) {
                $all_instances[] = ['url' => $purl, 'fname' => $fname];
                $aksi_html .= '<a href="' . htmlspecialchars($furl, ENT_QUOTES) . '" download '
                    . 'style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;background:#64748b;color:#fff;border-radius:5px;margin-right:3px;font-size:11px;" '
                    . 'title="Download"><i class="fa fa-download"></i></a>';
            }
        }
 
        $html .= '<tr>';
        $html .= '<td style="padding:8px 10px;">' . $series['series_number'] . '</td>';
        $html .= '<td style="padding:8px 10px;"><span style="background:#3b82f6;color:#fff;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;">' . htmlspecialchars($series['modality']) . '</span></td>';
        $html .= '<td style="padding:8px 10px;font-weight:600;">' . count($instances) . '</td>';
        $html .= '<td style="padding:8px 10px;">' . ($file_html ?: '-') . '</td>';
        $html .= '<td style="padding:8px 10px;">' . ($aksi_html ?: '-') . '</td>';
        $html .= '</tr>';
    }
 
    if (empty($series_list)) {
        $html .= '<tr><td colspan="5" style="text-align:center;padding:16px;color:#9ca3af;">Tidak ada series</td></tr>';
    }
 
    $html .= '</tbody></table>';
 
    echo json_encode([
        'success'       => true,
        'html'          => $html,
        'meta'          => $meta_arr,
        'all_instances' => $all_instances,
    ]);
    exit();
  }
 
  public function getDcmPreview($instance_id = '')
  {
    $instance = $this->db('mlite_mini_pacs_instance')->where('id', $instance_id)->oneArray();
    if (!$instance) { http_response_code(404); exit(); }
 
    $dcm_path = realpath(BASE_DIR) . '/' . $instance['file_path'];
    if (!file_exists($dcm_path)) { http_response_code(404); exit(); }
 
    $jpg_path = sys_get_temp_dir() . '/dcm_prev_' . $instance_id . '_' . md5($dcm_path) . '.jpg';
 
    if (!file_exists($jpg_path)) {
        exec(sprintf('dcmj2pnm +oj %s %s 2>&1', escapeshellarg($dcm_path), escapeshellarg($jpg_path)));
    }
 
    if (!file_exists($jpg_path)) { http_response_code(500); exit(); }
 
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($jpg_path));
    header('Cache-Control: no-store');
    readfile($jpg_path);
    @unlink($jpg_path);
    exit();
  }
 
  public function postSendImagingStudy()
  {
    header('Content-Type: application/json');
 
    $no_rawat = $_POST['no_rawat'] ?? '';
    $study_id = $_POST['study_id'] ?? '';
 
    if (!$no_rawat || !$study_id) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
        exit();
    }
 
    // Ambil data yang dibutuhkan
    $pacs_study  = $this->db('mlite_mini_pacs_study')->where('id', $study_id)->oneArray();
    $permintaan  = $this->db('permintaan_radiologi')->where('no_rawat', $no_rawat)->oneArray();
    $ss_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();
    $meta        = $this->getDicomMeta($no_rawat);
 
    if (!$pacs_study) {
        echo json_encode(['success' => false, 'message' => 'Study tidak ditemukan di Mini PACS']);
        exit();
    }
 
    if (empty($permintaan['noorder'])) {
        echo json_encode(['success' => false, 'message' => 'Permintaan radiologi tidak ditemukan']);
        exit();
    }
 
    if (empty($ss_response['id_encounter'])) {
        echo json_encode(['success' => false, 'message' => 'Encounter belum ada. Kirim Encounter dulu di halaman Data Response']);
        exit();
    }
 
    if (empty($ss_response['id_rad_request'])) {
        echo json_encode(['success' => false, 'message' => 'ServiceRequest Radiologi belum ada. Kirim Service Request dulu di halaman Data Response']);
        exit();
    }
 
    // Ambil token & praktisi
    $token     = json_decode($this->getToken())->access_token;
    $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $nm_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
    $id_dokter = $this->db('mlite_satu_sehat_mapping_praktisi')
        ->select('practitioner_id')->where('kd_dokter', $kd_dokter)->oneArray();
 
    $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $meta['no_rkm_medis']);
    $id_pasien     = '';
 
    if (!empty($no_ktp_pasien)) {
        $__patientResp = $this->getPatient($no_ktp_pasien);
        $__patientJson = json_decode($__patientResp);
        if (is_object($__patientJson) && !empty($__patientJson->entry)) {
            $id_pasien = $__patientJson->entry[0]->resource->id ?? '';
        }
    }
 
    // Fallback: ekstrak dari subject Encounter yang sudah tersimpan
    if (!$id_pasien && !empty($ss_response['id_encounter'])) {
        // GET Encounter dari Satu Sehat untuk ambil subject.reference (Patient ID)
        $enc_curl = curl_init();
        curl_setopt_array($enc_curl, [
            CURLOPT_URL            => $this->fhirurl . '/Encounter/' . $ss_response['id_encounter'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . json_decode($this->getToken())->access_token,
            ],
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_TIMEOUT        => 30,
        ]);
        $enc_raw  = curl_exec($enc_curl);
        curl_close($enc_curl);
        $enc_json = json_decode($enc_raw);
        if (isset($enc_json->subject->reference)) {
            $id_pasien = str_replace('Patient/', '', $enc_json->subject->reference);
        }
    }
 
    if (!$id_pasien) {
        echo json_encode([
            'success' => false,
            'message' => 'Patient ID Satu Sehat tidak ditemukan. NIK: ' . ($no_ktp_pasien ?: 'kosong') . '. Pastikan NIK terdaftar di Satu Sehat.',
        ]);
        exit();
    }
 
    // Ambil semua instances dari study ini
    $series_list = $this->db('mlite_mini_pacs_series')->where('study_id', $study_id)->toArray();
    $instances   = [];
    $total_inst  = 0;
 
    foreach ($series_list as $series) {
        $insts = $this->db('mlite_mini_pacs_instance')->where('series_id', $series['id'])->toArray();
        foreach ($insts as $inst) {
            $fp = realpath(BASE_DIR) . '/' . $inst['file_path'];
            if (!file_exists($fp)) continue;
            $instances[] = [
                'sop_instance_uid' => $inst['sop_instance_uid'],
                'sop_class_uid'    => $inst['sop_class_uid'],
                'instance_number'  => ++$total_inst,
            ];
        }
    }
 
    if (empty($instances)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada file DCM yang valid untuk dikirim']);
        exit();
    }
 
    // Timezone
    $zonawaktu = '+07:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WITA') $zonawaktu = '+08:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WIT')  $zonawaktu = '+09:00';
 
    $noorder    = $permintaan['noorder'];
    $study_uid  = $pacs_study['study_instance_uid'];
    $modality   = $pacs_study['modality'];
    $study_date = $pacs_study['study_date'];
    $series_uid = $series_list[0]['series_instance_uid'] ?? $this->generateDicomUid();
 
    // Build instances JSON
    $instances_json = '';
    foreach ($instances as $i => $inst) {
        $instances_json .= '{
            "uid": "' . $inst['sop_instance_uid'] . '",
            "sopClass": {
                "system": "urn:ietf:rfc:3986",
                "code": "urn:oid:' . $inst['sop_class_uid'] . '"
            },
            "number": ' . $inst['instance_number'] . '
        }' . ($i < count($instances) - 1 ? ',' : '');
    }
 
    // Build FHIR ImagingStudy payload
    $id_imaging_study = $ss_response['id_imaging_study'] ?? '';
 
    $payload = [
        'resourceType'     => 'ImagingStudy',
        'identifier'       => [
            [
                'system' => 'urn:dicom:uid',
                'value'  => 'urn:oid:' . $study_uid,
            ],
            [
                'type'   => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'ACSN']]],
                'system' => 'http://sys-ids.kemkes.go.id/acsn/' . $this->organizationid,
                'value'  => $noorder,
            ],
        ],
        'status'           => 'available',
        'modality'         => [['system' => 'http://dicom.nema.org/resources/ontology/DCM', 'code' => $modality]],
        'subject'          => ['reference' => 'Patient/' . $id_pasien, 'display' => $meta['nm_pasien']],
        'encounter'        => ['reference' => 'Encounter/' . $ss_response['id_encounter']],
        'started'          => date('Y-m-d', strtotime($study_date)) . 'T' . date('H:i:s', strtotime($study_date)) . $zonawaktu,
        'basedOn'          => [['reference' => 'ServiceRequest/' . $ss_response['id_rad_request']]],
        'referrer'         => ['reference' => 'Practitioner/' . ($id_dokter['practitioner_id'] ?? ''), 'display' => $nm_dokter],
        'numberOfSeries'   => count($series_list),
        'numberOfInstances'=> $total_inst,
        'series'           => [[
            'uid'               => $series_uid,
            'number'            => 1,
            'modality'          => ['system' => 'http://dicom.nema.org/resources/ontology/DCM', 'code' => $modality],
            'numberOfInstances' => $total_inst,
            'instance'          => json_decode('[' . $instances_json . ']'),
        ]],
    ];
 
    // PUT jika sudah ada, POST jika baru
    if ($id_imaging_study) {
        $payload['id'] = $id_imaging_study;
        $method  = 'PUT';
        $url     = $this->fhirurl . '/ImagingStudy/' . $id_imaging_study;
        $aksi    = 'UPDATE';
    } else {
        $method  = 'POST';
        $url     = $this->fhirurl . '/ImagingStudy';
        $aksi    = 'CREATE';
    }
 
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
 
    $response = curl_exec($curl);
    $decoded  = json_decode($response);
    curl_close($curl);
 
    $new_id = $decoded->id ?? null;
 
    if ($new_id) {
        if ($ss_response) {
            $this->db('mlite_satu_sehat_response')
                ->where('no_rawat', $no_rawat)
                ->save(['id_imaging_study' => $new_id]);
        } else {
            $this->db('mlite_satu_sehat_response')
                ->save(['no_rawat' => $no_rawat, 'id_imaging_study' => $new_id]);
        }
 
        echo json_encode([
            'success'          => true,
            'message'          => $aksi . ' ImagingStudy sukses! ID: ' . $new_id . ' | Total instances: ' . $total_inst,
            'id_imaging_study' => $new_id,
            'aksi'             => $aksi,
            'response'         => json_decode($response, true),
        ]);
    } else {
        echo json_encode([
            'success'  => false,
            'message'  => 'Gagal ' . $aksi . ' ImagingStudy ke Satu Sehat',
            'response' => json_decode($response, true),
        ]);
    }
    exit();
  }

public function postConvertDcm()
{
    header('Content-Type: application/json');
    $no_rawat = $_POST['no_rawat'] ?? '';
    $lokasi   = $_POST['lokasi_gambar'] ?? '';
    $modality = strtoupper(trim($_POST['modality'] ?? 'CR'));
    $desc     = trim($_POST['study_description'] ?? '') ?: $this->getDefaultStudyDescription($modality);
 
    if (!$no_rawat || !$lokasi) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
        exit();
    }
 
    $permintaan = $this->db('permintaan_radiologi')->where('no_rawat', $no_rawat)->oneArray();
    if (empty($permintaan['noorder'])) {
        echo json_encode(['success' => false, 'message' => 'Permintaan radiologi tidak ditemukan']);
        exit();
    }
 
    $noorder   = $permintaan['noorder'];
    $radbase   = realpath(WEBAPPS_PATH . '/radiologi') . '/';
    $src_path  = realpath($radbase . $lokasi);
 
    if (!$src_path || !file_exists($src_path)) {
        echo json_encode(['success' => false, 'message' => 'File gambar tidak ditemukan']);
        exit();
    }
 
    $pacs_base = realpath(BASE_DIR) . '/uploads/pacs/' . $noorder . '/';
    if (!is_dir($pacs_base)) mkdir($pacs_base, 0755, true);
 
    $study  = $this->getOrCreateStudy($no_rawat, $modality, $desc, $permintaan);
    $series = $this->getOrCreateSeries((int)$study['id'], $no_rawat, $modality);
    $meta   = $this->getDicomMeta($no_rawat);
 
    $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $nm_dokter = $this->db('dokter')
        ->select('nm_dokter')
        ->where('kd_dokter', $kd_dokter)
        ->oneArray()['nm_dokter'] ?? '';
    $instansi  = $this->settings->get('settings.nama_instansi') ?: '';
 
    $hash     = md5($lokasi);
    $dcm_file = $noorder . '_' . $hash . '.dcm';
    $dcm_path = $pacs_base . $dcm_file;
    $tmp_path = $pacs_base . 'tmp_' . $hash . '.dcm';
 
    $old = $this->db('mlite_mini_pacs_instance')
        ->where('series_id', $series['id'])
        ->where('file_path', 'uploads/pacs/' . $noorder . '/' . $dcm_file)
        ->oneArray();
    if ($old) {
        $this->db('mlite_mini_pacs_instance')->where('id', $old['id'])->delete();
        if (file_exists($dcm_path)) unlink($dcm_path);
    }
 
    $inst_no = count($this->db('mlite_mini_pacs_instance')->where('series_id', $series['id'])->toArray()) + 1;
    $sop_uid = $this->generateDicomUid();
    $sop_cls = '1.2.840.10008.5.1.4.1.1.1';
 
    $result = $this->convertImageToDicom(
        $src_path, $tmp_path, $dcm_path,
        $study['study_instance_uid'], $series['series_instance_uid'], $sop_uid, $sop_cls,
        $meta['nm_pasien_dicom'], $meta['no_rkm_medis'], $meta['tgl_lahir_dicom'], $meta['sex_dicom'],
        date('Ymd', strtotime($study['study_date'])), date('His', strtotime($study['study_date'])),
        $noorder, $modality, $desc, $inst_no,
        $nm_dokter, $instansi
    );
 
    if (!$result['success']) {
        echo json_encode(['success' => false, 'message' => $result['message']]);
        exit();
    }
 
    $this->db('mlite_mini_pacs_instance')->save([
        'series_id'        => $series['id'],
        'no_rawat'         => $no_rawat,
        'sop_instance_uid' => $sop_uid,
        'sop_class_uid'    => $sop_cls,
        'file_path'        => 'uploads/pacs/' . $noorder . '/' . $dcm_file,
    ]);
 
    echo json_encode(['success' => true, 'message' => 'Sukses!', 'dcm_filename' => $dcm_file]);
    exit();
  }
 
public function postUploadDicom()
{
    header('Content-Type: application/json');
    $no_rawat_raw = trim($_POST['no_rawat'] ?? '');
    $modality     = strtoupper(trim($_POST['modality'] ?? ''));
    $desc         = trim($_POST['study_description'] ?? '') ?: $this->getDefaultStudyDescription($modality);
 
    if (!$no_rawat_raw || !$modality) {
        echo json_encode(['success' => false, 'message' => 'No Rawat dan Modality wajib diisi']);
        exit();
    }
    if (empty($_FILES['file']['tmp_name'])) {
        echo json_encode(['success' => false, 'message' => 'File tidak ditemukan']);
        exit();
    }
 
    $nr_conv  = preg_replace('/[^0-9]/', '', $no_rawat_raw);
    $no_rawat = revertNoRawat($nr_conv);
    if (!$no_rawat) {
        echo json_encode(['success' => false, 'message' => 'Format No. Rawat tidak valid']);
        exit();
    }
 
    $permintaan = $this->db('permintaan_radiologi')->where('no_rawat', $no_rawat)->oneArray();
    if (empty($permintaan['noorder'])) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada permintaan radiologi']);
        exit();
    }
 
    $noorder   = $permintaan['noorder'];
    $pacs_base = realpath(BASE_DIR) . '/uploads/pacs/' . $noorder . '/';
    if (!is_dir($pacs_base)) mkdir($pacs_base, 0755, true);
 
    $study  = $this->getOrCreateStudy($no_rawat, $modality, $desc, $permintaan);
    $series = $this->getOrCreateSeries((int)$study['id'], $no_rawat, $modality);
    $meta   = $this->getDicomMeta($no_rawat);
 
    // Ambil nama dokter dari tabel dokter & instansi dari settings
    $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $nm_dokter = $this->db('dokter')
        ->select('nm_dokter')
        ->where('kd_dokter', $kd_dokter)
        ->oneArray()['nm_dokter'] ?? '';
    $instansi  = $this->settings->get('settings.nama_instansi') ?: '';
 
    $tmp  = $_FILES['file']['tmp_name'];
    $ext  = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $ino  = count($this->db('mlite_mini_pacs_instance')->where('series_id', $series['id'])->toArray()) + 1;
    $dcmf = $noorder . '_upload_' . time() . '_' . $ino . '.dcm';
    $dcmp = $pacs_base . $dcmf;
    $sop  = $this->generateDicomUid();
    $cls  = '1.2.840.10008.5.1.4.1.1.1';
 
    if ($ext === 'dcm') {
        move_uploaded_file($tmp, $dcmp);
        if (!file_exists($dcmp)) {
            echo json_encode(['success' => false, 'message' => 'Gagal simpan DCM']);
            exit();
        }
    } else {
        $srct = $pacs_base . 'src_' . $ino . '.' . $ext;
        $tmpt = $pacs_base . 'tmp_upload_' . $ino . '.dcm';
        move_uploaded_file($tmp, $srct);
        $res = $this->convertImageToDicom(
            $srct, $tmpt, $dcmp,
            $study['study_instance_uid'], $series['series_instance_uid'], $sop, $cls,
            $meta['nm_pasien_dicom'], $meta['no_rkm_medis'], $meta['tgl_lahir_dicom'], $meta['sex_dicom'],
            date('Ymd', strtotime($study['study_date'])), date('His', strtotime($study['study_date'])),
            $noorder, $modality, $desc, $ino,
            $nm_dokter, $instansi
        );
        if (file_exists($srct)) unlink($srct);
        if (!$res['success']) {
            echo json_encode(['success' => false, 'message' => $res['message']]);
            exit();
        }
    }
 
    $this->db('mlite_mini_pacs_instance')->save([
        'series_id'        => $series['id'],
        'no_rawat'         => $no_rawat,
        'sop_instance_uid' => $sop,
        'sop_class_uid'    => $cls,
        'file_path'        => 'uploads/pacs/' . $noorder . '/' . $dcmf,
    ]);
 
    echo json_encode(['success' => true, 'message' => 'Berhasil!', 'no_rawat_converted' => $nr_conv]);
    exit();
  }
 
public function postDeleteStudy()
{
    header('Content-Type: application/json');
    $sid = $_POST['study_id'] ?? '';
    if (!$sid) { echo json_encode(['success' => false, 'message' => 'study_id tidak ada']); exit(); }
 
    foreach ($this->db('mlite_mini_pacs_series')->where('study_id', $sid)->toArray() as $s) {
        foreach ($this->db('mlite_mini_pacs_instance')->where('series_id', $s['id'])->toArray() as $i) {
            $fp = realpath(BASE_DIR) . '/' . $i['file_path'];
            if (file_exists($fp)) unlink($fp);
        }
        $this->db('mlite_mini_pacs_instance')->where('series_id', $s['id'])->delete();
    }
    $this->db('mlite_mini_pacs_series')->where('study_id', $sid)->delete();
    $this->db('mlite_mini_pacs_study')->where('id', $sid)->delete();
 
    echo json_encode(['success' => true]);
    exit();
  }

  public function getToken()
  {
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->settings->get('satu_sehat.authurl') . '/accesstoken?grant_type=client_credentials',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => 'client_id=' . $this->clientid . '&client_secret=' . $this->secretkey,
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
    // echo $response;    
    // exit();
  }

  private function getAccessToken(): string
  {
    $raw = $this->getToken();
    $obj = json_decode($raw);
    if (is_object($obj) && isset($obj->access_token) && is_string($obj->access_token)) {
      return $obj->access_token;
    }
    return '';
  }

  public function getPractitioner($nik_dokter)
  {

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Practitioner?identifier=https://fhir.kemkes.go.id/id/nik|' . $nik_dokter,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'GET'
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
    // echo $response;
    // exit();

  }

  public function getDicomRouter()
  {

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api-satusehat.kemkes.go.id/dicom-router',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'GET'
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    echo '<pre>' . $response . '</pre>';
    exit();

  }

  public function getPractitionerID($nik_dokter)
  {
    echo json_decode($this->getPractitioner($nik_dokter))->entry[0]->resource->id;
    exit();
  }

  public function getPractitionerByID($id_dokter)
  {

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Practitioner/' . $id_dokter,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    echo $response;
    exit();
  }

  public function getPatient($nik_pasien)
  {

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Patient?identifier=https://fhir.kemkes.go.id/id/nik|' . $nik_pasien,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'GET'
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
    // echo $response;
    // exit();

  }

  public function getPatientID($nik_pasien)
  {
    echo json_decode($this->getPatient($nik_pasien))->entry[0]->resource->id;
    exit();
  }

  public function getPatientByID($id_pasien)
  {

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Patient/' . $id_pasien,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    echo $response;
    exit();
  }

  public function getOrganization($kode_departemen, $kode_organization = '')
  {
    $partOf = $this->organizationid;
    $nameResource = $this->core->getDepartemenInfo($kode_departemen);
    if ($kode_organization != '') {
      $partOf = $kode_organization;
      $nameResource = $this->core->getPoliklinikInfo('nm_poli', $kode_departemen);
    }
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Organization',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => '{
        "resourceType": "Organization",
        "active": true,
        "identifier": [
            {
                "use": "official",
                "system": "http://sys-ids.kemkes.go.id/organization/' . $this->organizationid . '",
                "value": "' . $kode_departemen . '"
            }
        ],
        "type": [
            {
                "coding": [
                    {
                        "system": "http://terminology.hl7.org/CodeSystem/organization-type",
                        "code": "dept",
                        "display": "Hospital Department"
                    }
                ]
            }
        ],
        "name": "' . $nameResource . '",
        "telecom": [
            {
                "system": "phone",
                "value": "' . $this->settings->get('settings.nomor_telepon') . '",
                "use": "work"
            },
            {
                "system": "email",
                "value": "' . $this->settings->get('settings.email') . '",
                "use": "work"
            },
            {
                "system": "url",
                "value": "www.' . $this->settings->get('settings.email') . '",
                "use": "work"
            }
        ],
        "address": [
            {
                "use": "work",
                "type": "both",
                "line": [
                    "' . $this->settings->get('settings.alamat') . '"
                ],
                "city": "' . $this->settings->get('settings.kota') . '",
                "postalCode": "' . $this->settings->get('satu_sehat.kodepos') . '",
                "country": "ID",
                "extension": [
                    {
                        "url": "https://fhir.kemkes.go.id/r4/StructureDefinition/administrativeCode",
                        "extension": [
                            {
                                "url": "province",
                                "valueCode": "' . $this->settings->get('satu_sehat.propinsi') . '"
                            },
                            {
                                "url": "city",
                                "valueCode": "' . $this->settings->get('satu_sehat.kabupaten') . '"
                            },
                            {
                                "url": "district",
                                "valueCode": "' . $this->settings->get('satu_sehat.kecamatan') . '"
                            },
                            {
                                "url": "village",
                                "valueCode": "' . $this->settings->get('satu_sehat.kelurahan') . '"
                            }
                        ]
                    }
                ]
            }
        ],
        "partOf": {
            "reference": "Organization/' . $partOf . '"
        }
    }',
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
    // echo $response;
    // exit();    
  }

  public function getOrganizationById($kode_departemen)
  {

    $mlite_satu_sehat_departemen = $this->db('mlite_satu_sehat_departemen')->where('dep_id', $kode_departemen)->oneArray();

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Organization/' . $mlite_satu_sehat_departemen['id_organisasi_satusehat'],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'GET',
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    echo $response;
    exit();
  }

  public function getOrganizationByPart($kode_departemen)
  {

    $mlite_satu_sehat_departemen = $this->db('mlite_satu_sehat_departemen')->where('dep_id', $kode_departemen)->oneArray();

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Organization?partof=' . $this->organizationid,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'GET',
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    echo $response;
    exit();
  }

  public function getOrganizationUpdate($kode_departemen)
  {

    $mlite_satu_sehat_departemen = $this->db('mlite_satu_sehat_departemen')->where('dep_id', $kode_departemen)->oneArray();

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Organization/' . $mlite_satu_sehat_departemen['id_organisasi_satusehat'],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'PUT',
      CURLOPT_POSTFIELDS => '{
        "resourceType": "Organization",
        "id":"' . $mlite_satu_sehat_departemen['id_organisasi_satusehat'] . '",
        "active": true,
        "identifier": [
            {
                "use": "official",
                "system": "http://sys-ids.kemkes.go.id/organization/' . $this->organizationid . '",
                "value": "' . $kode_departemen . '"
            }
        ],
        "type": [
            {
                "coding": [
                    {
                        "system": "http://terminology.hl7.org/CodeSystem/organization-type",
                        "code": "dept",
                        "display": "Hospital Department"
                    }
                ]
            }
        ],
        "name": "' . $this->core->getDepartemenInfo($kode_departemen) . '",
        "telecom": [
            {
                "system": "phone",
                "value": "' . $this->settings->get('settings.nomor_telepon') . '",
                "use": "work"
            },
            {
                "system": "email",
                "value": "' . $this->settings->get('settings.email') . '",
                "use": "work"
            },
            {
                "system": "url",
                "value": "www.' . $this->settings->get('settings.email') . '",
                "use": "work"
            }
        ],
        "address": [
            {
                "use": "work",
                "type": "both",
                "line": [
                    "' . $this->settings->get('settings.alamat') . '"
                ],
                "city": "' . $this->settings->get('settings.kota') . '",
                "postalCode": "' . $this->settings->get('satu_sehat.kodepos') . '",
                "country": "ID",
                "extension": [
                    {
                        "url": "https://fhir.kemkes.go.id/r4/StructureDefinition/administrativeCode",
                        "extension": [
                            {
                                "url": "province",
                                "valueCode": "' . $this->settings->get('satu_sehat.propinsi') . '"
                            },
                            {
                                "url": "city",
                                "valueCode": "' . $this->settings->get('satu_sehat.kabupaten') . '"
                            },
                            {
                                "url": "district",
                                "valueCode": "' . $this->settings->get('satu_sehat.kecamatan') . '"
                            },
                            {
                                "url": "village",
                                "valueCode": "' . $this->settings->get('satu_sehat.kelurahan') . '"
                            }
                        ]
                    }
                ]
            }
        ],
        "partOf": {
            "reference": "Organization/' . $this->settings->get('satu_sehat.organizationid') . '"
        }
    }',
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    echo $response;
    exit();
  }

  public function getLocation($kode, $kode_organization = '')
  {
    $lokasi = '';
    if (!empty($this->core->getPoliklinikInfo('nm_poli', $kode))) {
      $lokasi = $this->core->getPoliklinikInfo('nm_poli', $kode);
    } else {
      $lokasi = $this->core->getBangsalInfo('nm_bangsal', $kode);
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Location',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => '{
        "resourceType": "Location",
        "identifier": [
            {
                "system": "http://sys-ids.kemkes.go.id/location/' . $this->organizationid . '",
                "value": "' . $kode . '"
            }
        ],
        "status": "active",
        "name": "' . $lokasi . '",
        "description": "' . $kode . ' - ' . $lokasi . '",
        "mode": "instance",
        "telecom": [
          {
              "system": "phone",
              "value": "' . $this->settings->get('settings.nomor_telepon') . '",
              "use": "work"
          },
          {
              "system": "email",
              "value": "' . $this->settings->get('settings.email') . '",
              "use": "work"
          },
          {
              "system": "url",
              "value": "www.' . $this->settings->get('settings.email') . '",
              "use": "work"
          }
        ],
        "address": {
            "use": "work",
            "line": [
                "' . $this->settings->get('settings.alamat') . '"
            ],
            "city": "' . $this->settings->get('settings.kota') . '",
            "postalCode": "' . $this->settings->get('satu_sehat.kodepos') . '",
            "country": "ID",
            "extension": [
                {
                    "url": "https://fhir.kemkes.go.id/r4/StructureDefinition/administrativeCode",
                    "extension": [
                        {
                            "url": "province",
                            "valueCode": "' . $this->settings->get('satu_sehat.propinsi') . '"
                        },
                        {
                            "url": "city",
                            "valueCode": "' . $this->settings->get('satu_sehat.kabupaten') . '"
                        },
                        {
                            "url": "district",
                            "valueCode": "' . $this->settings->get('satu_sehat.kecamatan') . '"
                        },
                        {
                            "url": "village",
                            "valueCode": "' . $this->settings->get('satu_sehat.kelurahan') . '"
                        },
                        {
                            "url": "rt",
                            "valueCode": "1"
                        },
                        {
                            "url": "rw",
                            "valueCode": "2"
                        }
                    ]
                }
            ]
        },
        "physicalType": {
            "coding": [
                {
                    "system": "http://terminology.hl7.org/CodeSystem/location-physical-type",
                    "code": "ro",
                    "display": "Room"
                }
            ]
        },
        "position": {
            "longitude": ' . $this->settings->get('satu_sehat.longitude') . ',
            "latitude": ' . $this->settings->get('satu_sehat.latitude') . ',
            "altitude": 0
      },
        "managingOrganization": {
            "reference": "Organization/' . $kode_organization . '"
        }
    }',
    ));

    $response = curl_exec($curl);

    if (json_decode($response)->issue[0]->code == 'duplicate') {
      $curl = curl_init();
      curl_setopt_array($curl, array(
        CURLOPT_URL => $this->settings->get('satu_sehat.fhirurl') . '/Location?organization=' . $kode_organization,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
        CURLOPT_CUSTOMREQUEST => 'GET',
      ));

      $response = curl_exec($curl);
    }

    curl_close($curl);

    return $response;
    // echo $response;
  }

  public function getLocationByOrgId($kode_departemen)
  {

    $mlite_satu_sehat_lokasi = $this->db('mlite_satu_sehat_lokasi')->where('kode', $kode_departemen)->oneArray();

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Location?organization=' . $mlite_satu_sehat_lokasi['id_lokasi_satusehat'],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'GET',
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    echo $response;
    exit();
  }

  public function getLocationUpdate($kode_departemen)
  {

    $mlite_satu_sehat_lokasi = $this->db('mlite_satu_sehat_lokasi')->where('kode', $kode_departemen)->oneArray();

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Location/' . $mlite_satu_sehat_lokasi['id_lokasi_satusehat'],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'PUT',
      CURLOPT_POSTFIELDS => '{
        "resourceType": "Location",
        "id": "' . $mlite_satu_sehat_lokasi['id_lokasi_satusehat'] . '",
        "identifier": [
            {
                "system": "http://sys-ids.kemkes.go.id/location/' . $this->organizationid . '",
                "value": "' . $mlite_satu_sehat_lokasi['kode'] . '"
            }
        ],
        "status": "inactive",
        "name": "' . $mlite_satu_sehat_lokasi['lokasi'] . '",
        "description": "' . $mlite_satu_sehat_lokasi['kode'] . ' - ' . $mlite_satu_sehat_lokasi['lokasi'] . '",
        "mode": "instance",
        "telecom": [
            {
                "system": "phone",
                "value": "' . $this->settings->get('settings.nomor_telepon') . '",
                "use": "work"
            },
            {
                "system": "fax",
                "value": "' . $this->settings->get('settings.nomor_telepon') . '",
                "use": "work"
            },
            {
                "system": "email",
                "value": "' . $this->settings->get('settings.email') . '"
            },
            {
                "system": "url",
                "value": "' . $this->settings->get('settings.website') . '",
                "use": "work"
            }
        ],
        "address": {
            "use": "work",
            "line": [
                "' . $this->settings->get('settings.alamat') . '"
            ],
            "city": "' . $this->settings->get('settings.kota') . '",
            "postalCode": "' . $this->settings->get('satu_sehat.kodepos') . '",
            "country": "ID",
            "extension": [
                {
                    "url": "https://fhir.kemkes.go.id/r4/StructureDefinition/administrativeCode",
                    "extension": [
                        {
                            "url": "province",
                            "valueCode": "' . $this->settings->get('satu_sehat.propinsi') . '"
                        },
                        {
                            "url": "city",
                            "valueCode": "' . $this->settings->get('satu_sehat.kabupaten') . '"
                        },
                        {
                            "url": "district",
                            "valueCode": "' . $this->settings->get('satu_sehat.kecamatan') . '"
                        },
                        {
                            "url": "village",
                            "valueCode": "' . $this->settings->get('satu_sehat.kelurahan') . '"
                        },
                        {
                            "url": "rt",
                            "valueCode": "1"
                        },
                        {
                            "url": "rw",
                            "valueCode": "2"
                        }
                    ]
                }
            ]
        },
        "physicalType": {
            "coding": [
                {
                    "system": "http://terminology.hl7.org/CodeSystem/location-physical-type",
                    "code": "ro",
                    "display": "Room"
                }
            ]
        },
        "position": {
            "longitude": -6.23115426275766,
            "latitude": 106.83239885393944,
            "altitude": 0
        },
        "managingOrganization": {
            "reference": "Organization/' . $this->settings->get('satu_sehat.organizationid') . '"
        }
    }',
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    echo $response;
    exit();
  }


  public function getEncounter($no_rawat, $render = true)
  {
    $zonawaktu = '+07:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WITA') {
      $zonawaktu = '+08:00';
    }
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WIT') {
      $zonawaktu = '+09:00';
    }

    $no_rawat = revertNoRawat($no_rawat);
    $kd_poli = $this->core->getRegPeriksaInfo('kd_poli', $no_rawat);
    $nm_poli = $this->core->getPoliklinikInfo('nm_poli', $kd_poli);
    $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $no_ktp_dokter = $this->db('mlite_satu_sehat_mapping_praktisi')->select('practitioner_id')->where('kd_dokter', $kd_dokter)->oneArray();
    $nama_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
    $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
    $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
    $nama_pasien = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
    $status_lanjut = $this->core->getRegPeriksaInfo('status_lanjut', $no_rawat);
    $tgl_registrasi = $this->core->getRegPeriksaInfo('tgl_registrasi', $no_rawat);
    $jam_reg = $this->core->getRegPeriksaInfo('jam_reg', $no_rawat);
    $endTime = date("H:i:s", strtotime('+10 minutes', strtotime($jam_reg)));

    $code = 'AMB';
    $display = 'ambulatory';
    if ($status_lanjut == 'Ranap') {
      $kd_poli = $this->core->getKamarInapInfo('kd_kamar', $no_rawat);
      $kd_bangsal = $this->core->getKamarInfo('kd_bangsal', $kd_poli);
      $nm_poli = $this->core->getBangsalInfo('nm_bangsal', $kd_bangsal);
      $code = 'IMP';
      $display = 'inpatient encounter';
    }

    if ($status_lanjut == 'Ranap') {
      $mapping_lokasi = $this->db('satu_sehat_mapping_lokasi_ranap')
        ->where('kd_kamar', $kd_poli)->oneArray();
    } else {
      $mapping_lokasi = $this->db('satu_sehat_mapping_lokasi_ralan')
        ->where('kd_poli', $kd_poli)->oneArray();
    }
    $lokasi_id = isset($mapping_lokasi['id_lokasi_satusehat']) ? $mapping_lokasi['id_lokasi_satusehat'] : '';
    $praktisi_id = isset($no_ktp_dokter['practitioner_id']) ? $no_ktp_dokter['practitioner_id'] : '';

    $__patientResp = $this->getPatient($no_ktp_pasien);
    $__patientJson = json_decode($__patientResp);
    $ihs_patient = '';
    if (is_object($__patientJson) && isset($__patientJson->entry) && is_array($__patientJson->entry) && isset($__patientJson->entry[0]) && isset($__patientJson->entry[0]->resource) && isset($__patientJson->entry[0]->resource->id)) {
      $ihs_patient = $__patientJson->entry[0]->resource->id;
    }

    $curl = curl_init();
    $json = '{
      "resourceType": "Encounter",
      "status": "arrived",
      "class": {
          "system": "http://terminology.hl7.org/CodeSystem/v3-ActCode",
          "code": "' . $code . '",
          "display": "' . $display . '"
      },
      "subject": {
          "reference": "Patient/' . $ihs_patient . '",
          "display": "' . $nama_pasien . '"
      },
      "participant": [
          {
              "type": [
                  {
                      "coding": [
                          {
                              "system": "http://terminology.hl7.org/CodeSystem/v3-ParticipationType",
                              "code": "ATND",
                              "display": "attender"
                          }
                      ]
                  }
              ],
              "individual": {
                  "reference": "Practitioner/' . $praktisi_id . '",
                  "display": "' . $nama_dokter . '"
              }
          }
      ],
      "period": {
          "start": "' . $tgl_registrasi . 'T' . $jam_reg . '' . $zonawaktu . '"
      },
      "location": [
          {
              "location": {
                  "reference": "Location/' . $lokasi_id . '",
                  "display": "' . $kd_poli . ' ' . $nm_poli . '"
              }
          }
      ],
      "statusHistory": [
          {
              "status": "arrived",
              "period": {
                  "start": "' . $tgl_registrasi . 'T' . $jam_reg . '' . $zonawaktu . '",
                  "end": "' . $tgl_registrasi . 'T' . $endTime . '' . $zonawaktu . '"
              }
          }
      ],
      "serviceProvider": {
          "reference": "Organization/' . $this->organizationid . '"
      },
      "identifier": [
          {
              "system": "http://sys-ids.kemkes.go.id/encounter/' . $this->organizationid . '",
              "value": "' . $no_rawat . '"
          }
      ]
    }';
    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Encounter',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $json,
    ));

    $response = curl_exec($curl);

    $decoded = json_decode($response);
    $id_encounter = (is_object($decoded) && isset($decoded->id)) ? $decoded->id : null;
    $pesan = 'Gagal mengirim encounter platform Satu Sehat!!';
    if ($id_encounter) {
      $this->db('mlite_satu_sehat_response')->save([
        'no_rawat' => $no_rawat,
        'id_encounter' => $id_encounter
      ]);
      $pesan = 'Sukses mengirim encounter platform Satu Sehat!!';
    }

    curl_close($curl);
    if ($render) {
      echo $this->draw('encounter.html', ['pesan' => $pesan, 'response' => $response, 'json' => $json]);
    } else {
      $data = json_decode($response);
      echo $data ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $response;
    }
    exit();
  }

  public function getEncounterById($encounter_id, $type) 
  {
   if ($type == '') {
      $type = 'ClinicalImpression';
    }
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/' . $type . '?encounter=' . $encounter_id,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'GET',
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $result = json_decode($response, true);

    echo $result['entry'][0]['resource']['id'] ?? null;
    exit();
  }

  public function getEncounterBundle($no_rawat, $param = '')
  {
    ob_start();
 
    $src_path = __DIR__ . '/src/';
    foreach ([
        'Composition', 'Respiratory', 'Temperature', 'Observation',
        'Condition', 'Procedure', 'CarePlan', 'ClinicalImpression',
        'Medication', 'MedicationRequest', 'MedicationDispense',
        'QuestionareMedication', 'ServiceRequest', 'Specimen', 'DiagnosticReport',
    ] as $cls) {
        if (file_exists($src_path . $cls . '.php')) require_once $src_path . $cls . '.php';
    }
 
    $zonawaktu = '+07:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WITA') $zonawaktu = '+08:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WIT')  $zonawaktu = '+09:00';
 
    $no_rawat       = revertNoRawat($no_rawat);
    $kd_poli        = $this->core->getRegPeriksaInfo('kd_poli', $no_rawat);
    $nm_poli        = $this->core->getPoliklinikInfo('nm_poli', $kd_poli);
    $kd_dokter      = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $no_ktp_dokter  = $this->db('mlite_satu_sehat_mapping_praktisi')->select('practitioner_id')->where('kd_dokter', $kd_dokter)->oneArray();
    $nama_dokter    = $this->core->getPegawaiInfo('nama', $kd_dokter);
    $no_rkm_medis   = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
    $no_ktp_pasien  = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
    $nama_pasien    = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
    $status_lanjut  = $this->core->getRegPeriksaInfo('status_lanjut', $no_rawat);
    $tgl_registrasi = $this->core->getRegPeriksaInfo('tgl_registrasi', $no_rawat);
    $jam_reg        = $this->core->getRegPeriksaInfo('jam_reg', $no_rawat);
 
    $inProg = $this->db('pemeriksaan_ralan')
        ->select(['tgl' => 'tgl_perawatan', 'jam' => 'jam_rawat', 'respirasi' => 'respirasi',
                  'suhu' => 'suhu_tubuh', 'tensi' => 'tensi', 'nadi' => 'nadi',
                  'penilaian' => 'penilaian', 'keluhan' => 'keluhan'])
        ->where('no_rawat', $no_rawat)->oneArray();
 
    $diagnosa_pasien = $this->db('diagnosa_pasien')
        ->join('penyakit', 'penyakit.kd_penyakit=diagnosa_pasien.kd_penyakit')
        ->where('no_rawat', $no_rawat)
        ->where('diagnosa_pasien.status', $status_lanjut)
        ->where('prioritas', '1')
        ->oneArray();
 
    $_prosedure_pasien = $this->db('prosedur_pasien')
        ->select(['deskripsi_pendek' => 'icd9.deskripsi_pendek', 'kode' => 'icd9.kode'])
        ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
        ->where('prosedur_pasien.no_rawat', $no_rawat)
        ->where('prosedur_pasien.status', 'Ralan')
        ->where('prosedur_pasien.prioritas', '1')
        ->oneArray();
    $prosedure_pasien      = $_prosedure_pasien['deskripsi_pendek'] ?? '';
    $kode_prosedure_pasien = $_prosedure_pasien['kode'] ?? '';
    if ($kode_prosedure_pasien !== '' && strpos($kode_prosedure_pasien, '.') === false && strlen($kode_prosedure_pasien) >= 2) {
        $kode_prosedure_pasien = substr_replace($kode_prosedure_pasien, '.', 2, 0);
    }
 
    $billing_row = $this->db('billing')->where('no_rawat', $no_rawat)->desc('tgl_byr')->oneArray();
    $tgl_billing = $billing_row['tgl_byr'] ?? $tgl_registrasi;
    $jam_billing = '23:59:59';
 
    $kunjungan = 'Kunjungan'; $code = 'AMB'; $display = 'ambulatory';
    if ($status_lanjut == 'Ranap') {
        $kunjungan  = 'Perawatan'; $code = 'IMP'; $display = 'inpatient encounter';
        $kd_poli    = $this->core->getKamarInapInfo('kd_kamar', $no_rawat);
        $kd_bangsal = $this->core->getKamarInfo('kd_bangsal', $kd_poli);
        $nm_poli    = $this->core->getBangsalInfo('nm_bangsal', $kd_bangsal);
    }
 
    if ($status_lanjut == 'Ranap') {
        $lokasi_row = $this->db('satu_sehat_mapping_lokasi_ranap')->where('kd_kamar', $kd_poli)->oneArray();
        if (empty($lokasi_row)) $lokasi_row = $this->db('satu_sehat_mapping_lokasi_ranap')->oneArray();
    } else {
        $lokasi_row = $this->db('satu_sehat_mapping_lokasi_ralan')->where('kd_poli', $kd_poli)->oneArray();
        if (empty($lokasi_row)) $lokasi_row = $this->db('satu_sehat_mapping_lokasi_ralan')->oneArray();
    }
    $id_lokasi_satusehat = $lokasi_row['id_lokasi_satusehat'] ?? '';
 
    $uuid_encounter                     = $this->gen_uuid();
    $uuid_condition                     = $this->gen_uuid();
    $uuid_respiration                   = $this->gen_uuid();
    $uuid_suhu                          = $this->gen_uuid();
    $uuid_sistolik                      = $this->gen_uuid();
    $uuid_diastolik                     = $this->gen_uuid();
    $uuid_nadi                          = $this->gen_uuid();
    $uuid_procedure                     = $this->gen_uuid();
    $uuid_composition                   = $this->gen_uuid();
    $uuid_clinical_impression_history   = $this->gen_uuid();
    $uuid_clinical_impression_prognosis = $this->gen_uuid();
 
    $ihs_patient   = '';
    $__patientJson = json_decode($this->getPatient($no_ktp_pasien));
    if (is_object($__patientJson) && isset($__patientJson->entry[0]->resource->id)) {
        $ihs_patient = $__patientJson->entry[0]->resource->id;
    }
 
    $tensi    = $inProg['tensi'] ?? '';
    $sistole  = strtok($tensi, '/');
    $diastole = substr($tensi, strpos($tensi, '/') + 1);
 
    $diastole_json = ''; $sistole_json = '';
    if (!empty($tensi) && $tensi !== '-') {
        $tgl_jam_ttv = $this->convertTimeSatset(($inProg['tgl'] ?? $tgl_registrasi) . ' ' . ($inProg['jam'] ?? '00:00:00')) . $zonawaktu;
        $diastole_json = '{
        "fullUrl": "urn:uuid:' . $uuid_diastolik . '",
        "resource": {
            "resourceType": "Observation","status": "final",
            "category": [{"coding": [{"system": "http://terminology.hl7.org/CodeSystem/observation-category","code": "vital-signs","display": "Vital Signs"}]}],
            "code": {"coding": [{"system": "http://loinc.org","code": "8462-4","display": "Diastolic blood pressure"}]},
            "subject": {"reference": "Patient/' . $ihs_patient . '"},
            "performer": [{"reference": "Practitioner/' . ($no_ktp_dokter['practitioner_id'] ?? '') . '"}],
            "encounter": {"reference": "urn:uuid:' . $uuid_encounter . '","display": "Pemeriksaan Fisik Diastolik ' . $nama_pasien . ' di ' . $tgl_registrasi . '"},
            "effectiveDateTime": "' . $tgl_jam_ttv . '","issued": "' . $tgl_jam_ttv . '",
            "bodySite": {"coding": [{"system": "http://snomed.info/sct","code": "368209003","display": "Right arm"}]},
            "valueQuantity": {"value": ' . (float)$diastole . ',"unit": "mm[Hg]","system": "http://unitsofmeasure.org","code": "mm[Hg]"},
            "interpretation": [{"coding": [{"system": "http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation","code": "L","display": "low"}],"text": "Di bawah nilai referensi"}]
        },"request": {"method": "POST","url": "Observation"}},';
        $sistole_json = '{
        "fullUrl": "urn:uuid:' . $uuid_sistolik . '",
        "resource": {
            "resourceType": "Observation","status": "final",
            "category": [{"coding": [{"system": "http://terminology.hl7.org/CodeSystem/observation-category","code": "vital-signs","display": "Vital Signs"}]}],
            "code": {"coding": [{"system": "http://loinc.org","code": "8480-6","display": "Systolic blood pressure"}]},
            "subject": {"reference": "Patient/' . $ihs_patient . '"},
            "performer": [{"reference": "Practitioner/' . ($no_ktp_dokter['practitioner_id'] ?? '') . '"}],
            "encounter": {"reference": "urn:uuid:' . $uuid_encounter . '","display": "Pemeriksaan Fisik Sistole ' . $nama_pasien . ' di ' . $tgl_registrasi . '"},
            "effectiveDateTime": "' . $tgl_jam_ttv . '","issued": "' . $tgl_jam_ttv . '",
            "valueQuantity": {"value": ' . (float)$sistole . ',"unit": "mm[Hg]","system": "http://unitsofmeasure.org","code": "mm[Hg]"}
        },"request": {"method": "POST","url": "Observation"}},';
    }
 
    $composition_json = '';
    try {
        $zonaWaktu_composition = $this->convertTimeSatset($tgl_billing . ' ' . $jam_billing) . $zonawaktu;
        $composition = new Composition($uuid_encounter, $uuid_composition, $ihs_patient, $no_ktp_dokter['practitioner_id'] ?? '', $nama_pasien, $nama_dokter, $no_rawat, $this->organizationid, "Kunjungan " . $nama_pasien . " di tanggal " . $tgl_registrasi, $zonaWaktu_composition);
        $composition_json = $composition->toJson();
    } catch (\Exception $e) {}
 
    $respiratory_json = '';
    try {
        if (!in_array($inProg['respirasi'] ?? '', ['', '-'])) {
            $zonaWaktu = $this->convertTimeSatset(($inProg['tgl'] ?? $tgl_registrasi) . ' ' . ($inProg['jam'] ?? '00:00:00')) . $zonawaktu;
            $respiratory = new Respiratory($uuid_encounter, $uuid_respiration, $ihs_patient, $no_ktp_dokter['practitioner_id'] ?? '', $inProg['respirasi'], $zonaWaktu, "Pemeriksaan Fisik Pernafasan " . $nama_pasien . " di " . $tgl_registrasi);
            $respiratory_json = $respiratory->toJson();
        }
    } catch (\Exception $e) {}
 
    $temperatur_json = '';
    try {
        if (!empty($inProg['suhu'])) {
            $value_temp = 'N'; $display_temp = 'Normal'; $text_temp = 'antara';
            if ($inProg['suhu'] > 37) { $value_temp = 'H'; $display_temp = 'High'; $text_temp = 'atas'; }
            if ($inProg['suhu'] < 36) { $value_temp = 'L'; $display_temp = 'Low';  $text_temp = 'bawah'; }
            $zonaWaktu = $this->convertTimeSatset(($inProg['tgl'] ?? $tgl_registrasi) . ' ' . ($inProg['jam'] ?? '00:00:00')) . $zonawaktu;
            $suhu = new Temperature($uuid_encounter, $uuid_suhu, $ihs_patient, $no_ktp_dokter['practitioner_id'] ?? '', str_replace(',', '.', $inProg['suhu']), "Pemeriksaan Fisik Suhu " . $nama_pasien . " di " . $tgl_registrasi, $value_temp, $display_temp, $text_temp, $zonaWaktu);
            $temperatur_json = $suhu->toJson();
        }
    } catch (\Exception $e) {}
 
    $heart_rate_json = '';
    try {
        if (!in_array($inProg['nadi'] ?? '', ['', '-'])) {
            $zonaWaktu = $this->convertTimeSatset(($inProg['tgl'] ?? $tgl_registrasi) . ' ' . ($inProg['jam'] ?? '00:00:00')) . $zonawaktu;
            $nadi = new Observation($uuid_encounter, $uuid_nadi, $ihs_patient, $no_ktp_dokter['practitioner_id'] ?? '', $inProg['nadi'], $zonaWaktu, "Pemeriksaan Fisik Nadi " . $nama_pasien . " di " . $tgl_registrasi, 'nadi');
            $heart_rate_json = $nadi->toJsonBundle() . ',';
        }
    } catch (\Exception $e) {}
 
    $condition_json = '';
    try {
        if (!empty($diagnosa_pasien['kd_penyakit'])) {
            $condition = new Condition($uuid_encounter, $uuid_condition, $diagnosa_pasien['kd_penyakit'], $diagnosa_pasien['nm_penyakit'], $ihs_patient, $nama_pasien, $kunjungan . ' ' . $nama_pasien . ' dari tanggal ' . $tgl_registrasi);
            $condition_json = $condition->toJson();
        }
    } catch (\Exception $e) {}
 
    $procedure_json = '';
    try {
        if ($prosedure_pasien) {
            $zonaWaktu = $this->convertTimeSatset(($inProg['tgl'] ?? $tgl_registrasi) . ' ' . ($inProg['jam'] ?? '00:00:00')) . $zonawaktu;
            $procedure = new Procedure($uuid_encounter, $uuid_procedure, $kode_prosedure_pasien, $prosedure_pasien, $ihs_patient, $nama_pasien, "Tindakan pada " . $nama_pasien . " di tanggal " . $tgl_registrasi, $zonaWaktu, $no_ktp_dokter['practitioner_id'] ?? '', $nama_dokter, $diagnosa_pasien['kd_penyakit'] ?? '', $diagnosa_pasien['nm_penyakit'] ?? '');
            $procedure_json = $procedure->toJson();
        }
    } catch (\Exception $e) {}
 
    $careplan_json = '';
    try {
        $cek_ranap = $this->db('kamar_inap')->where('no_rawat', $no_rawat)->oneArray();
        if ($cek_ranap) {
            $uuid_careplan = $this->gen_uuid();
            $careplan = new CarePlan($ihs_patient, $uuid_encounter, $uuid_careplan, "Pasien Dirawat Inapkan", "Rawat Inap", $no_ktp_dokter['practitioner_id'] ?? '');
            $careplan_json = $careplan->toJsonBundle() . ',';
        }
    } catch (\Exception $e) {}
 
    $medicationforrequest_json = ''; $medicationrequest_json = ''; $medicationrequest_ids = [];
    try {
        $no = 1;
        $cek_resep = $this->db('resep_dokter')->join('resep_obat', 'resep_obat.no_resep = resep_dokter.no_resep')->where('no_rawat', $no_rawat)->where('status', 'ralan')->toArray();
        foreach ($cek_resep as $value) {
            try {
                $cek_obat = $this->db('satu_sehat_mapping_obat')->where('kode_brng', $value['kode_brng'])->oneArray();
                if ($cek_obat) {
                    $uuid_medication = $this->gen_uuid(); $uuid_medicationrequest = $this->gen_uuid();
                    $system_cek = in_array($cek_obat['satuan_den'], ['385057009', '421366001']) ? 'http://snomed.info/sct' : 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm';
                    $medication = new Medication($uuid_medication, $this->organizationid, $no_rawat, $cek_obat['kode_kfa'], $cek_obat['nama_kfa'], $cek_obat['kode_sediaan'], $cek_obat['nama_sediaan'], $cek_obat['kode_bahan'], $cek_obat['nama_bahan'], $cek_obat['satuan_num'], $cek_obat['numerator'], $cek_obat['satuan_den'], $system_cek, $value['jml'], $no . $this->ran_char());
                    $medicationforrequest_json .= $medication->toJson();
                    $time_authored = $this->convertTimeSatset($value['tgl_peresepan'] . ' ' . $value['jam_peresepan']) . $zonawaktu;
                    $medicationrequest = new MedicationRequest($uuid_medication, $uuid_medicationrequest, $this->organizationid, $value['no_resep'], $cek_obat['nama_kfa'], $ihs_patient, $nama_pasien, $time_authored, $no_ktp_dokter['practitioner_id'] ?? '', $nama_dokter, $uuid_condition, $uuid_encounter, $diagnosa_pasien['nm_penyakit'] ?? '', $value['aturan_pakai'], $cek_obat['kode_route'], $cek_obat['nama_route'], $value['jml'], $cek_obat['satuan_den'], $system_cek, $cek_obat['satuan_den'], $no . $this->ran_char());
                    $medicationrequest_json .= $medicationrequest->toJson();
                    if (!empty($value['kode_brng'])) { $medicationrequest_ids[$value['kode_brng']] = $uuid_medicationrequest; }
                    $no++;
                }
            } catch (\Exception $e) { continue; }
        }
    } catch (\Exception $e) {}
 
    $medicationfordispense_json = ''; $medicationdispense_json = '';
    try {
        $no = 1;
        $cek_detail = $this->db('detail_pemberian_obat')->where('no_rawat', $no_rawat)->toArray();
        $praktisi_apoteker = $this->db('mlite_satu_sehat_mapping_praktisi')->select('practitioner_id', 'kd_dokter')->where('jenis_praktisi', 'Apoteker')->toArray();
        $id_praktisi_apoteker = (!is_array($praktisi_apoteker) || empty($praktisi_apoteker))
            ? ['practitioner_id' => $no_ktp_dokter['practitioner_id'] ?? '', 'kd_dokter' => $kd_dokter]
            : $praktisi_apoteker[array_rand($praktisi_apoteker)];
        $nama_praktisi_apoteker = $this->core->getPegawaiInfo('nama', $id_praktisi_apoteker['kd_dokter'] ?? $kd_dokter);
        foreach ($cek_detail as $value) {
            try {
                $cek_obat = $this->db('satu_sehat_mapping_obat')->where('kode_brng', $value['kode_brng'])->oneArray();
                $cek_aturan_pakai = $this->db('aturan_pakai')->where('no_rawat', $no_rawat)->where('kode_brng', $value['kode_brng'])->where('jam', $value['jam'])->oneArray();
                if ($cek_obat && $cek_aturan_pakai) {
                    $uuid_medication_for_dispense = $this->gen_uuid(); $uuid_medication_dispense = $this->gen_uuid();
                    $system_cek = in_array($cek_obat['satuan_den'], ['385057009', '421366001']) ? 'http://snomed.info/sct' : 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm';
                    $medication_for_dispense = new Medication($uuid_medication_for_dispense, $this->organizationid, $no_rawat, $cek_obat['kode_kfa'], $cek_obat['nama_kfa'], $cek_obat['kode_sediaan'], $cek_obat['nama_sediaan'], $cek_obat['kode_bahan'], $cek_obat['nama_bahan'], $cek_obat['satuan_num'], $cek_obat['numerator'], $cek_obat['satuan_den'], $system_cek, $value['jml'], $no . $this->ran_char());
                    $medicationfordispense_json .= $medication_for_dispense->toJson();
                    $time_prepared_handed = $this->convertTimeSatset($value['tgl_perawatan'] . ' ' . $value['jam']) . $zonawaktu;
                    $mr_uuid_for_dispense = $medicationrequest_ids[$value['kode_brng']] ?? $this->gen_uuid();
                    $medication_dispense = new MedicationDispense($uuid_medication_dispense, $this->organizationid, $no_rawat, $uuid_medication_for_dispense, $cek_obat['nama_kfa'], $ihs_patient, $nama_pasien, $uuid_encounter, $id_praktisi_apoteker['practitioner_id'] ?? '', $nama_praktisi_apoteker, $id_lokasi_satusehat, $mr_uuid_for_dispense, $time_prepared_handed, $time_prepared_handed, $cek_aturan_pakai['aturan'] ?? '', $value['jml'], $cek_obat['satuan_den'], $system_cek, $cek_obat['satuan_den'], $no . $this->ran_char());
                    $medicationdispense_json .= $medication_dispense->toJson();
                    $no++;
                }
            } catch (\Exception $e) { continue; }
        }
    } catch (\Exception $e) {}
 
    $questionare_json = ''; $careplan_medication_json = '';
    try {
        if ($medicationdispense_json) {
            $uuid_questionare = $this->gen_uuid();
            $questionare = new QuestionareMedication($uuid_questionare, $uuid_encounter, $ihs_patient, $nama_pasien, $id_praktisi_apoteker['practitioner_id'] ?? '', $nama_praktisi_apoteker);
            $questionare_json = $questionare->toJson();
            $uuid_careplan = $this->gen_uuid();
            $careplan = new CarePlan($ihs_patient, $uuid_encounter, $uuid_careplan, "Pasien Mendapatkan Resep Obat", "Resep Obat", $no_ktp_dokter['practitioner_id'] ?? '');
            $careplan_medication_json = $careplan->toJsonBundle() . ',';
        }
    } catch (\Exception $e) {}
 
    $clinical_impression_json_history = '';
    try {
        if (!empty($inProg['keluhan'])) {
            $clinicalimpression_history = new ClinicalImpression($this->organizationid, $uuid_clinical_impression_history, $no_rawat, $ihs_patient, $nama_pasien, $uuid_encounter, $inProg['keluhan'], "in-progress");
            $clinical_impression_json_history = $clinicalimpression_history->toJsonBundle() . ',';
        }
    } catch (\Exception $e) {}
 
    $clinical_impression_json_prognosis = '';
    try {
        if (!empty($inProg['penilaian'])) {
            $clinicalimpression = new ClinicalImpression($this->organizationid, $uuid_clinical_impression_prognosis, $no_rawat, $ihs_patient, $nama_pasien, $uuid_encounter, $inProg['penilaian'], "completed");
            $clinical_impression_json_prognosis = $clinicalimpression->toJsonBundle() . ',';
        }
    } catch (\Exception $e) {}
 
    // ==== Lab ====
    $service_request_lab_json = []; $specimen_json = []; $observation_lab_json = []; $diagnostic_report_json = [];
    try {
        $permintaan_lab = $this->db('permintaan_lab')->join('permintaan_pemeriksaan_lab', 'permintaan_lab.noorder = permintaan_pemeriksaan_lab.noorder')->where('no_rawat', $no_rawat)->toArray();
        foreach ($permintaan_lab as $value) {
            try {
                $check_mapping_lab = $this->db('mlite_satu_sehat_mapping_lab')->where('kd_jenis_prw', $value['kd_jenis_prw'])->oneArray();
                if ($check_mapping_lab && $check_mapping_lab['id_template'] != '' && $check_mapping_lab['jenis_pemeriksaan'] == 'tunggal') {
                    $praktisi_lab          = $this->db('mlite_satu_sehat_mapping_praktisi')->select('practitioner_id', 'kd_dokter')->where('jenis_praktisi', 'Laboratorium')->toArray();
                    $id_praktisi_lab       = $praktisi_lab[array_rand($praktisi_lab)];
                    $no_ktp_dokter_perujuk = $this->db('mlite_satu_sehat_mapping_praktisi')->select('practitioner_id')->where('kd_dokter', $value['dokter_perujuk'])->oneArray();
                    $nama_dokter_perujuk   = $this->core->getPegawaiInfo('nama', $value['dokter_perujuk']);
                    $nama_tindakan         = $this->db('jns_perawatan_lab')->select('nm_perawatan')->where('kd_jenis_prw', $value['kd_jenis_prw'])->oneArray();
                    $time_sampled          = $this->convertTimeSatset($value['tgl_sample'] . ' ' . $value['jam_sample']) . $zonawaktu;
                    $time_result           = $this->convertTimeSatset($value['tgl_hasil'] . ' ' . $value['jam_hasil']) . $zonawaktu;
                    $uuid_service_request_lab = $this->gen_uuid(); $uuid_specimen_lab = $this->gen_uuid();
                    $uuid_observation_lab     = $this->gen_uuid(); $uuid_diagnostic_report = $this->gen_uuid();
                    $service_request_lab = new ServiceRequest($uuid_service_request_lab, $this->organizationid, $no_rawat, $ihs_patient, $uuid_encounter, $no_ktp_dokter_perujuk['practitioner_id'] ?? '', $nama_dokter_perujuk, $check_mapping_lab['code_loinc'], $check_mapping_lab['display_loinc'], $check_mapping_lab['code_kptl'], $check_mapping_lab['display_kptl'], $nama_tindakan['nm_perawatan'] ?? '');
                    $service_request_lab_json[] = $service_request_lab->toJsonBundle() . ',';
                    $specimen_lab = new Specimen($uuid_specimen_lab, $this->organizationid, $ihs_patient, $nama_pasien, $uuid_service_request_lab, $no_rawat, $time_sampled);
                    $specimen_json[] = $specimen_lab->toJsonBundle() . ',';
                    $cek_hasil_lab = $this->db('detail_periksa_lab')->where('no_rawat', $no_rawat)->where('kd_jenis_prw', $value['kd_jenis_prw'])->where('tgl_periksa', $value['tgl_hasil'])->where('jam', $value['jam_hasil'])->oneArray();
                    $observation_lab = new Observation($uuid_encounter, $uuid_observation_lab, $ihs_patient, $id_praktisi_lab, $cek_hasil_lab['nilai'] ?? '', $time_result, "", "lab", $uuid_specimen_lab, $uuid_service_request_lab, $check_mapping_lab['code_loinc'], $check_mapping_lab['display_loinc']);
                    $observation_lab_json[] = $observation_lab->toJsonBundle() . ',';
                    $diagnostic_report_lab = new DiagnosticReport($uuid_diagnostic_report, $uuid_specimen_lab, $uuid_encounter, $uuid_service_request_lab, $uuid_observation_lab, $id_praktisi_lab, $ihs_patient, $check_mapping_lab['code_loinc'], $check_mapping_lab['display_loinc'], $time_result);
                    $diagnostic_report_json[] = $diagnostic_report_lab->toJsonBundle() . ',';
                }
            } catch (\Exception $e) { continue; }
        }
    } catch (\Exception $e) {}
 
    $service_request_lab_json_decode = implode("\n", $service_request_lab_json);
    $specimen_lab_json_decode        = implode("\n", $specimen_json);
    $observation_lab_json_decode     = implode("\n", $observation_lab_json);
    $diagnostic_report_json_decode   = implode("\n", $diagnostic_report_json);
 
    $careplan_service_request_lab_json = '';
    try {
        if ($service_request_lab_json_decode) {
            $uuid_careplan = $this->gen_uuid();
            $careplan = new CarePlan($ihs_patient, $uuid_encounter, $uuid_careplan, "Pasien Mendapatkan Pemeriksaan Laboratorium", "Pemeriksaan Laboratorium", $no_ktp_dokter['practitioner_id'] ?? '');
            $careplan_service_request_lab_json = $careplan->toJsonBundle() . ',';
        }
    } catch (\Exception $e) {}
 
    // ==== Radiologi ====
    $service_request_rad_json = []; $specimen_rad_json = [];
    $observation_rad_json = []; $diagnostic_report_rad_json = [];
    try {
        $permintaan_rad = $this->db('permintaan_radiologi')
            ->join('permintaan_pemeriksaan_radiologi', 'permintaan_pemeriksaan_radiologi.noorder = permintaan_radiologi.noorder')
            ->where('permintaan_radiologi.no_rawat', $no_rawat)
            ->toArray();
        foreach ($permintaan_rad as $rad) {
            try {
                $mapping_rad = $this->db('satu_sehat_mapping_radiologi')->where('kd_jenis_prw', $rad['kd_jenis_prw'])->oneArray();
                if (!$mapping_rad) continue;
                $perujuk_rad  = $this->db('mlite_satu_sehat_mapping_praktisi')->select('practitioner_id')->where('kd_dokter', $rad['dokter_perujuk'])->oneArray();
                $nm_perujuk_r = $this->core->getPegawaiInfo('nama', $rad['dokter_perujuk']);
                $time_smp_r   = $this->convertTimeSatset($rad['tgl_sampel'] . ' ' . $rad['jam_sampel']) . $zonawaktu;
                $time_res_r   = $this->convertTimeSatset($rad['tgl_hasil']  . ' ' . $rad['jam_hasil'])  . $zonawaktu;
                $hasil_rad    = $this->db('hasil_radiologi')->where('no_rawat', $no_rawat)->where('tgl_periksa', $rad['tgl_hasil'])->where('jam', $rad['jam_hasil'])->oneArray();
                $nilai_rad    = addslashes($hasil_rad['hasil'] ?? '');
                $uuid_sr_r = $this->gen_uuid(); $uuid_sp_r = $this->gen_uuid();
                $uuid_ob_r = $this->gen_uuid(); $uuid_dr_r = $this->gen_uuid();
 
                $service_request_rad_json[] = '{
                "fullUrl":"urn:uuid:' . $uuid_sr_r . '","resource":{"resourceType":"ServiceRequest","status":"completed","intent":"original-order",
                "category":[{"coding":[{"system":"http://snomed.info/sct","code":"363679005","display":"Imaging"}]}],
                "code":{"coding":[{"system":"' . $mapping_rad['system'] . '","code":"' . $mapping_rad['code'] . '","display":"' . $mapping_rad['display'] . '"}]},
                "subject":{"reference":"Patient/' . $ihs_patient . '","display":"' . $nama_pasien . '"},
                "encounter":{"reference":"urn:uuid:' . $uuid_encounter . '"},
                "requester":{"reference":"Practitioner/' . ($perujuk_rad['practitioner_id'] ?? '') . '","display":"' . $nm_perujuk_r . '"},
                "reasonCode":[{"text":"' . addslashes($rad['diagnosa_klinis']) . '"}]
                },"request":{"method":"POST","url":"ServiceRequest"}},';
 
                $specimen_rad_json[] = '{
                "fullUrl":"urn:uuid:' . $uuid_sp_r . '","resource":{"resourceType":"Specimen","status":"available",
                "type":{"coding":[{"system":"' . $mapping_rad['sampel_system'] . '","code":"' . $mapping_rad['sampel_code'] . '","display":"' . $mapping_rad['sampel_display'] . '"}]},
                "subject":{"reference":"Patient/' . $ihs_patient . '","display":"' . $nama_pasien . '"},
                "request":[{"reference":"urn:uuid:' . $uuid_sr_r . '"}],
                "collection":{"collector":{"reference":"Practitioner/' . ($perujuk_rad['practitioner_id'] ?? '') . '"},"collectedDateTime":"' . $time_smp_r . '"}
                },"request":{"method":"POST","url":"Specimen"}},';
 
                $observation_rad_json[] = '{
                "fullUrl":"urn:uuid:' . $uuid_ob_r . '","resource":{"resourceType":"Observation","status":"final",
                "category":[{"coding":[{"system":"http://terminology.hl7.org/CodeSystem/observation-category","code":"imaging","display":"Imaging"}]}],
                "code":{"coding":[{"system":"' . $mapping_rad['system'] . '","code":"' . $mapping_rad['code'] . '","display":"' . $mapping_rad['display'] . '"}]},
                "subject":{"reference":"Patient/' . $ihs_patient . '"},"encounter":{"reference":"urn:uuid:' . $uuid_encounter . '"},
                "effectiveDateTime":"' . $time_res_r . '","issued":"' . $time_res_r . '",
                "performer":[{"reference":"Practitioner/' . ($perujuk_rad['practitioner_id'] ?? '') . '"}],
                "specimen":{"reference":"urn:uuid:' . $uuid_sp_r . '"},"basedOn":[{"reference":"urn:uuid:' . $uuid_sr_r . '"}]
                ' . ($nilai_rad ? ',"valueString":"' . $nilai_rad . '"' : '') . '
                },"request":{"method":"POST","url":"Observation"}},';
 
                $diagnostic_report_rad_json[] = '{
                "fullUrl":"urn:uuid:' . $uuid_dr_r . '","resource":{"resourceType":"DiagnosticReport","status":"final",
                "category":[{"coding":[{"system":"http://loinc.org","code":"18748-4","display":"Diagnostic imaging study"}]}],
                "code":{"coding":[{"system":"' . $mapping_rad['system'] . '","code":"' . $mapping_rad['code'] . '","display":"' . $mapping_rad['display'] . '"}]},
                "subject":{"reference":"Patient/' . $ihs_patient . '"},"encounter":{"reference":"urn:uuid:' . $uuid_encounter . '"},
                "effectiveDateTime":"' . $time_res_r . '","issued":"' . $time_res_r . '",
                "performer":[{"reference":"Practitioner/' . ($perujuk_rad['practitioner_id'] ?? '') . '"}],
                "specimen":[{"reference":"urn:uuid:' . $uuid_sp_r . '"}],"result":[{"reference":"urn:uuid:' . $uuid_ob_r . '"}],
                "basedOn":[{"reference":"urn:uuid:' . $uuid_sr_r . '"}]
                ' . ($nilai_rad ? ',"conclusion":"' . $nilai_rad . '"' : '') . '
                },"request":{"method":"POST","url":"DiagnosticReport"}},';
 
            } catch (\Exception $e) { continue; }
        }
    } catch (\Exception $e) {}
 
    $service_request_rad_str   = implode("\n", $service_request_rad_json);
    $specimen_rad_str          = implode("\n", $specimen_rad_json);
    $observation_rad_str       = implode("\n", $observation_rad_json);
    $diagnostic_report_rad_str = implode("\n", $diagnostic_report_rad_json);
 
    // ==== Build Bundle ====
    $json_bundle = '{
    "resourceType": "Bundle","type": "transaction",
    "entry": [{
        "fullUrl": "urn:uuid:' . $uuid_encounter . '",
        "resource": {
            "resourceType": "Encounter","status": "finished",
            "class": {"system": "http://terminology.hl7.org/CodeSystem/v3-ActCode","code": "' . $code . '","display": "' . $display . '"},
            "subject": {"reference": "Patient/' . $ihs_patient . '","display": "' . $nama_pasien . '"},
            "participant": [{"type": [{"coding": [{"system": "http://terminology.hl7.org/CodeSystem/v3-ParticipationType","code": "ATND","display": "attender"}]}],"individual": {"reference": "Practitioner/' . ($no_ktp_dokter['practitioner_id'] ?? '') . '","display": "' . $nama_dokter . '"}}],
            "period": {
                "start": "' . $this->convertTimeSatset($tgl_registrasi . ' ' . $jam_reg) . $zonawaktu . '",
                "end": "' . $this->convertTimeSatset($tgl_billing . ' ' . $jam_billing) . $zonawaktu . '"
            },
            "location": [{"location": {"reference": "Location/' . $id_lokasi_satusehat . '","display": "' . $kd_poli . ' ' . $nm_poli . '"}}],
            ' . (!empty($diagnosa_pasien['kd_penyakit']) ? '"diagnosis": [{"condition": {"reference": "urn:uuid:' . $uuid_condition . '","display": "' . ($diagnosa_pasien['nm_penyakit'] ?? '') . '"},"use": {"coding": [{"system": "http://terminology.hl7.org/CodeSystem/diagnosis-role","code": "DD","display": "Discharge diagnosis"}]},"rank": 1}],' : '') . '
            "statusHistory": [
                {"status": "arrived","period": {"start": "' . $this->convertTimeSatset($tgl_registrasi . ' ' . $jam_reg) . $zonawaktu . '","end": "' . $this->convertTimeSatset(($inProg['tgl'] ?? $tgl_registrasi) . ' ' . ($inProg['jam'] ?? '00:00:00')) . $zonawaktu . '"}},
                {"status": "in-progress","period": {"start": "' . $this->convertTimeSatset(($inProg['tgl'] ?? $tgl_registrasi) . ' ' . ($inProg['jam'] ?? '00:00:00')) . $zonawaktu . '","end": "' . $this->convertTimeSatset($tgl_billing . ' ' . $jam_billing) . $zonawaktu . '"}},
                {"status": "finished","period": {"start": "' . $this->convertTimeSatset($tgl_billing . ' ' . $jam_billing) . $zonawaktu . '","end": "' . $this->convertTimeSatset($tgl_billing . ' ' . $jam_billing) . $zonawaktu . '"}}
            ],
            "serviceProvider": {"reference": "Organization/' . $this->organizationid . '"},
            "identifier": [{"system": "http://sys-ids.kemkes.go.id/encounter/' . $this->organizationid . '","value": "' . $no_rawat . '"}]
        },"request": {"method": "POST","url": "Encounter"}
    },' .
    $diastole_json . $sistole_json . $respiratory_json . $temperatur_json . $heart_rate_json .
    $clinical_impression_json_history . $careplan_json . $careplan_service_request_lab_json .
    $service_request_lab_json_decode . $specimen_lab_json_decode . $observation_lab_json_decode . $diagnostic_report_json_decode .
    $service_request_rad_str . $specimen_rad_str . $observation_rad_str . $diagnostic_report_rad_str .
    $condition_json . $procedure_json . $careplan_medication_json . $medicationforrequest_json .
    $medicationrequest_json . $questionare_json . $medicationfordispense_json . $medicationdispense_json .
    $clinical_impression_json_prognosis . $composition_json . ']}';
              
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $this->fhirurl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . json_decode($this->getToken())->access_token],
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $json_bundle,
    ]);
    $response = curl_exec($curl);
    curl_close($curl);
 
    $id_encounter = ''; $id_condition = ''; $id_observation_respiratory = null;
    $id_observation_temp = null; $id_observation_nadi = null; $id_procedure = null;
    $id_composition = null; $id_medication_request = null; $id_medication_dispense = null;
    $id_clinical_impression = null; $id_service_request_lab = null; $id_specimen_lab = null;
    $id_observation_lab = null; $id_diagnostic_report_lab = null; $id_careplan = null;
    $id_rad_request = null; $id_rad_specimen = null; $id_rad_observation = null; $id_rad_diagnostic = null;
 
    $decodedResponse = json_decode($response);
    $entry = (is_object($decodedResponse) && isset($decodedResponse->entry) && is_array($decodedResponse->entry))
        ? $decodedResponse->entry : [];
 
    $index = '';
    foreach ((array)$entry as $key => $value) {
        $resourceType = $value->response->resourceType ?? null;
        if ($resourceType === null) continue;
        $index .= ' { ' . $key . '. ' . $resourceType . ' } ';
        if ($resourceType == 'Encounter')          { $id_encounter           = $value->response->resourceID; }
        if ($resourceType == 'Condition')          { $id_condition           = $value->response->resourceID; }
        if ($resourceType == 'Procedure')          { $id_procedure           = $value->response->resourceID; }
        if ($resourceType == 'Composition')        { $id_composition         = $value->response->resourceID; }
        if ($resourceType == 'MedicationRequest')  { $id_medication_request  = $value->response->resourceID; }
        if ($resourceType == 'MedicationDispense') { $id_medication_dispense = $value->response->resourceID; }
        if ($resourceType == 'ClinicalImpression') { $id_clinical_impression = $value->response->resourceID; }
        if ($resourceType == 'CarePlan')           { $id_careplan            = $value->response->resourceID; }
        if ($resourceType == 'ServiceRequest')     {
            if ($service_request_rad_str && !$id_rad_request)   { $id_rad_request = $value->response->resourceID; }
            elseif (!$id_service_request_lab)                    { $id_service_request_lab = $value->response->resourceID; }
        }
        if ($resourceType == 'Specimen') {
            if ($specimen_rad_str && !$id_rad_specimen)          { $id_rad_specimen = $value->response->resourceID; }
            elseif (!$id_specimen_lab)                           { $id_specimen_lab = $value->response->resourceID; }
        }
        if ($resourceType == 'DiagnosticReport') {
            if ($diagnostic_report_rad_str && !$id_rad_diagnostic) { $id_rad_diagnostic = $value->response->resourceID; }
            elseif (!$id_diagnostic_report_lab)                    { $id_diagnostic_report_lab = $value->response->resourceID; }
        }
        if ($resourceType == 'Observation') {
            if (!empty($respiratory_json) && !empty($temperatur_json) && !empty($heart_rate_json)) {
                if ($key == '1') { $id_observation_respiratory = $value->response->resourceID; }
                if ($key == '2') { $id_observation_temp        = $value->response->resourceID; }
                if ($key == '3') { $id_observation_nadi        = $value->response->resourceID; }
            } elseif (!empty($respiratory_json) && !empty($temperatur_json)) {
                if ($key == '1') { $id_observation_respiratory = $value->response->resourceID; }
                if ($key == '2') { $id_observation_temp        = $value->response->resourceID; }
            } elseif (!empty($respiratory_json) && !empty($heart_rate_json)) {
                if ($key == '1') { $id_observation_respiratory = $value->response->resourceID; }
                if ($key == '2') { $id_observation_nadi        = $value->response->resourceID; }
            } elseif (!empty($temperatur_json) && !empty($heart_rate_json)) {
                if ($key == '1') { $id_observation_temp = $value->response->resourceID; }
                if ($key == '2') { $id_observation_nadi = $value->response->resourceID; }
            } elseif (!empty($respiratory_json)) { if ($key == '1') { $id_observation_respiratory = $value->response->resourceID; } }
            elseif (!empty($temperatur_json))    { if ($key == '1') { $id_observation_temp        = $value->response->resourceID; } }
            elseif (!empty($heart_rate_json))    { if ($key == '1') { $id_observation_nadi        = $value->response->resourceID; } }
            if ($observation_lab_json_decode && !$id_observation_lab) { $id_observation_lab = $value->response->resourceID; }
            if ($observation_rad_str && !$id_rad_observation)         { $id_rad_observation = $value->response->resourceID; }
        }
    }
 
    $pesan = 'Gagal mengirim pasien dengan No Rawat : ' . $no_rawat . ' ke platform Satu Sehat!!';
    if ($id_encounter) {
        $this->db('mlite_satu_sehat_response')->save([
            'no_rawat'                    => $no_rawat,
            'id_encounter'                => $id_encounter,
            'id_condition'                => $id_condition,
            'id_observation_ttvrespirasi' => $id_observation_respiratory,
            'id_observation_ttvsuhu'      => $id_observation_temp,
            'id_observation_ttvnadi'      => $id_observation_nadi,
            'id_procedure'                => $id_procedure,
            'id_composition'              => $id_composition,
            'id_medication_request'       => $id_medication_request,
            'id_medication_dispense'      => $id_medication_dispense,
            'id_clinical_impression'      => $id_clinical_impression,
            'id_lab_pk_request'           => $id_service_request_lab,
            'id_lab_pk_specimen'          => $id_specimen_lab,
            'id_lab_pk_observation'       => $id_observation_lab,
            'id_lab_pk_diagnostic'        => $id_diagnostic_report_lab,
            'id_rad_request'              => $id_rad_request,
            'id_rad_specimen'             => $id_rad_specimen,
            'id_rad_observation'          => $id_rad_observation,
            'id_rad_diagnostic'           => $id_rad_diagnostic,
            'id_careplan'                 => $id_careplan,
        ]);
        $pesan = 'Sukses mengirim pasien dengan No Rawat : ' . $no_rawat . ' ke platform Satu Sehat!!';
    }
 
    $result = [
        'success'   => !empty($id_encounter),
        'pesan'     => $pesan,
        'no_rawat'  => $no_rawat,
        'nm_pasien' => $nama_pasien,
        'resources' => [
            ['label' => 'Encounter',             'id' => $id_encounter,              'sent' => !empty($id_encounter)],
            ['label' => 'Condition (Diagnosa)',  'id' => $id_condition,              'sent' => !empty($id_condition),              'skip' => empty($condition_json)],
            ['label' => 'Tensi',                 'id' => $uuid_sistolik,             'sent' => (!empty($tensi) && $tensi !== '-'), 'skip' => (empty($tensi) || $tensi === '-')],
            ['label' => 'Respirasi',             'id' => $id_observation_respiratory,'sent' => !empty($id_observation_respiratory),'skip' => empty($respiratory_json)],
            ['label' => 'Suhu',                  'id' => $id_observation_temp,       'sent' => !empty($id_observation_temp),       'skip' => empty($temperatur_json)],
            ['label' => 'Nadi',                  'id' => $id_observation_nadi,       'sent' => !empty($id_observation_nadi),       'skip' => empty($heart_rate_json)],
            ['label' => 'Procedure',             'id' => $id_procedure,              'sent' => !empty($id_procedure),              'skip' => empty($procedure_json)],
            ['label' => 'Clinical Impression',   'id' => $id_clinical_impression,    'sent' => !empty($id_clinical_impression),    'skip' => (empty($clinical_impression_json_history) && empty($clinical_impression_json_prognosis))],
            ['label' => 'Medication (Resep)',    'id' => $id_medication_request,     'sent' => !empty($id_medication_request),     'skip' => empty($medicationrequest_json)],
            ['label' => 'Medication Dispense',   'id' => $id_medication_dispense,    'sent' => !empty($id_medication_dispense),    'skip' => empty($medicationdispense_json)],
            ['label' => 'Lab Service Request',   'id' => $id_service_request_lab,    'sent' => !empty($id_service_request_lab),    'skip' => empty($service_request_lab_json_decode)],
            ['label' => 'Lab Specimen',          'id' => $id_specimen_lab,           'sent' => !empty($id_specimen_lab),           'skip' => empty($specimen_lab_json_decode)],
            ['label' => 'Lab Observation',       'id' => $id_observation_lab,        'sent' => !empty($id_observation_lab),        'skip' => empty($observation_lab_json_decode)],
            ['label' => 'Lab Diagnostic Report', 'id' => $id_diagnostic_report_lab,  'sent' => !empty($id_diagnostic_report_lab),  'skip' => empty($diagnostic_report_json_decode)],
            ['label' => 'Rad Service Request',   'id' => $id_rad_request,            'sent' => !empty($id_rad_request),            'skip' => empty($service_request_rad_str)],
            ['label' => 'Rad Specimen',          'id' => $id_rad_specimen,           'sent' => !empty($id_rad_specimen),           'skip' => empty($specimen_rad_str)],
            ['label' => 'Rad Observation',       'id' => $id_rad_observation,        'sent' => !empty($id_rad_observation),        'skip' => empty($observation_rad_str)],
            ['label' => 'Rad Diagnostic Report', 'id' => $id_rad_diagnostic,         'sent' => !empty($id_rad_diagnostic),         'skip' => empty($diagnostic_report_rad_str)],
            ['label' => 'Composition',           'id' => $id_composition,            'sent' => !empty($id_composition)],
            ['label' => 'Care Plan',             'id' => $id_careplan,               'sent' => !empty($id_careplan),               'skip' => (empty($careplan_json) && empty($careplan_medication_json))],
        ],
        'raw_response' => json_decode($response, true),
    ];
 
    if ($param == 'all') {
        ob_end_clean();
        return json_decode($response);
    } elseif (isset($_GET['format']) && $_GET['format'] === 'json') {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        header('Cache-Control: no-cache');
        echo json_encode($result);
        exit();
    } else {
        ob_end_clean();
        echo $this->draw('encounter.html', ['result' => $result, 'pesan' => $pesan, 'response' => $response]);
        exit();
    }
  }

  public function getSearchPasienBunban($periode = '', $nama = '')
{
    ob_start();
    if (!$periode) $periode = date('Y-m-d');
    $nama = urldecode($nama);
    if ($nama === '-') $nama = '';
 
    $query = $this->db('reg_periksa')
        ->join('pasien', 'pasien.no_rkm_medis = reg_periksa.no_rkm_medis')
        ->where('reg_periksa.tgl_registrasi', $periode)
        ->where('reg_periksa.stts', '!=', 'Batal')
        ->where('reg_periksa.kd_poli', '!=', 'IGD01');
 
    if (!empty($nama)) {
        $query = $query->where('pasien.nm_pasien', 'LIKE', '%' . $nama . '%');
    }
 
    $rows = $query->toArray();
 
    $result = [];
    foreach ($rows as $row) {
        if (!in_array($row['status_lanjut'], ['Ralan', 'Ranap'])) continue;
        $ss     = $this->db('mlite_satu_sehat_response')->where('no_rawat', $row['no_rawat'])->oneArray();
        $status = !empty($ss['id_encounter']) ? 'done' : 'pending';
        $result[] = [
            'no_rawat'        => $row['no_rawat'],
            'no_rkm_medis'    => $row['no_rkm_medis'],
            'nm_pasien'       => $row['nm_pasien'],
            'status_lanjut'   => $row['status_lanjut'],
            'status'          => $status,
            'no_rawat_key'    => str_replace('/', '', $row['no_rawat']),
        ];
    }
 
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
    echo json_encode(['success' => true, 'data' => $result, 'total' => count($result)]);
    exit();
}
 
// ================================================================
// postStartBunban — Daftarkan pasien ke job queue
// POST /satu_sehat/startbunban
// no_rawat_list[] → dari pencarian (opsional)
// batch_size      → fallback kalau tidak ada pencarian
// ================================================================
public function postStartBunban()
{
    ob_start();
    $periode        = $_POST['periode']    ?? date('Y-m-d');
    $batch_size     = $_POST['batch_size'] ?? '10';
    $no_rawat_list  = $_POST['no_rawat_list'] ?? []; // array dari checkbox
 
    $this->db('satu_sehat_job_queue')->where('periode', $periode)->delete();
 
    // Mode A: user pilih manual dari pencarian
    if (!empty($no_rawat_list)) {
        $pasien_list = [];
        foreach ($no_rawat_list as $nrc) {
            $no_rawat     = revertNoRawat($nrc);
            $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
            $nm_pasien    = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
            $status_lanjut = $this->core->getRegPeriksaInfo('status_lanjut', $no_rawat);
            if (!in_array($status_lanjut, ['Ralan', 'Ranap'])) continue;
            $pasien_list[] = ['no_rawat' => $no_rawat, 'nm_pasien' => $nm_pasien];
        }
    } else {
        // Mode B: ambil otomatis sesuai batch_size
        $query = $this->db('reg_periksa')
            ->where('tgl_registrasi', $periode)
            ->where('stts', '!=', 'Batal')
            ->where('kd_poli', '!=', 'IGD01');
        if ($batch_size !== 'all') $query = $query->limit((int)$batch_size);
        $rows = $query->toArray();
        $pasien_list = [];
        foreach ($rows as $row) {
            if (!in_array($row['status_lanjut'], ['Ralan', 'Ranap'])) continue;
            $nm_pasien = $this->core->getPasienInfo('nm_pasien', $row['no_rkm_medis']);
            $pasien_list[] = ['no_rawat' => $row['no_rawat'], 'nm_pasien' => $nm_pasien];
        }
    }
 
    // Insert ke job_queue — cek status dari mlite_satu_sehat_response
    foreach ($pasien_list as $p) {
        $ss     = $this->db('mlite_satu_sehat_response')->where('no_rawat', $p['no_rawat'])->oneArray();
        $status = !empty($ss['id_encounter']) ? 'skip' : 'pending';
        $this->db('satu_sehat_job_queue')->save([
            'periode'   => $periode,
            'no_rawat'  => $p['no_rawat'],
            'nm_pasien' => $p['nm_pasien'],
            'status'    => $status,
        ]);
    }
 
    $all     = $this->db('satu_sehat_job_queue')->where('periode', $periode)->toArray();
    $total   = count($all);
    $pending = count(array_filter($all, fn($r) => $r['status'] === 'pending'));
    $skip    = count(array_filter($all, fn($r) => $r['status'] === 'skip'));
 
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
    echo json_encode([
        'success'    => true,
        'periode'    => $periode,
        'total'      => $total,
        'pending'    => $pending,
        'done'       => $skip,
        'sukses'     => 0,
        'skip'       => $skip,
        'error'      => 0,
        'all'        => array_map(fn($r) => [
            'no_rawat'    => $r['no_rawat'],
            'nm_pasien'   => $r['nm_pasien'],
            'status'      => $r['status'],
            'detail_json' => $r['detail_json'] ? json_decode($r['detail_json'], true) : [],
            'error_msg'   => $r['error_msg'],
        ], $all),
    ]);
    exit();
}
 
// ================================================================
// getProcessBunban — Proses 1 pasien
// GET /satu_sehat/processbunban/{no_rawat_converted}
// ================================================================
public function getProcessBunban($no_rawat_converted = '')
{
    ob_start();
 
    if (!$no_rawat_converted) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'no_rawat tidak ada']);
        exit();
    }
 
    $no_rawat     = revertNoRawat($no_rawat_converted);
    $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
    $nm_pasien    = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
 
    $this->db('satu_sehat_job_queue')->where('no_rawat', $no_rawat)->save(['status' => 'processing']);
 
    try {
        $result_raw = $this->getEncounterBundle(convertNorawat($no_rawat), 'all');
        $result_arr = json_decode(json_encode($result_raw), true);
 
        $id_encounter = '';
        $resources    = [];
 
        if (!empty($result_arr['entry'])) {
            foreach ($result_arr['entry'] as $entry) {
                $resource_type = $entry['response']['resourceType'] ?? null;
                $resource_id   = $entry['response']['resourceID']   ?? '';
                $status_code   = $entry['response']['status']        ?? '';
                if (!$resource_type) continue;
                $sent = strpos($status_code, '201') !== false || strpos($status_code, '200') !== false;
                if ($resource_type === 'Encounter' && $sent) $id_encounter = $resource_id;
                $resources[] = [
                    'label' => $resource_type,
                    'id'    => $resource_id,
                    'sent'  => $sent,
                    'skip'  => false,
                    'error' => $sent ? '' : ($entry['response']['outcome']['issue'][0]['details']['text'] ?? 'Gagal'),
                ];
            }
        }
 
        if (!empty($result_arr['issue'])) {
            $error_msg = $result_arr['issue'][0]['details']['text'] ?? 'Unknown error';
            $detail    = [['label' => 'Bundle', 'sent' => false, 'error' => $error_msg]];
            $this->db('satu_sehat_job_queue')->where('no_rawat', $no_rawat)->save([
                'status' => 'error', 'error_msg' => $error_msg, 'detail_json' => json_encode($detail),
            ]);
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            header('Cache-Control: no-cache');
            echo json_encode(['success' => false, 'status' => 'error', 'no_rawat' => $no_rawat, 'nm_pasien' => $nm_pasien, 'message' => $error_msg, 'resources' => $detail]);
            exit();
        }
 
        $final_status = $id_encounter ? 'done' : 'error';
        $this->db('satu_sehat_job_queue')->where('no_rawat', $no_rawat)->save([
            'status'      => $final_status,
            'detail_json' => json_encode($resources),
            'error_msg'   => $id_encounter ? null : 'Encounter gagal terkirim',
        ]);
 
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        header('Cache-Control: no-cache');
        echo json_encode([
            'success'   => !empty($id_encounter),
            'status'    => $final_status,
            'no_rawat'  => $no_rawat,
            'nm_pasien' => $nm_pasien,
            'message'   => $id_encounter ? 'Sukses!' : 'Encounter gagal terkirim',
            'resources' => $resources,
        ]);
        exit();
 
    } catch (\Exception $e) {
        $detail = [['label' => 'Exception', 'sent' => false, 'error' => $e->getMessage()]];
        $this->db('satu_sehat_job_queue')->where('no_rawat', $no_rawat)->save([
            'status' => 'error', 'error_msg' => $e->getMessage(), 'detail_json' => json_encode($detail),
        ]);
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        header('Cache-Control: no-cache');
        echo json_encode(['success' => false, 'status' => 'error', 'no_rawat' => $no_rawat, 'nm_pasien' => $nm_pasien, 'message' => $e->getMessage(), 'resources' => $detail]);
        exit();
    }
}
 
// ================================================================
// getStatusBunban — Summary progress per periode
// GET /satu_sehat/statusbunban/{periode}
// ================================================================
public function getStatusBunban($periode = '')
{
    ob_start();
    if (!$periode) $periode = date('Y-m-d');
 
    $all    = $this->db('satu_sehat_job_queue')->where('periode', $periode)->toArray();
    $total  = count($all);
    $done   = 0; $sukses = 0; $skip = 0; $error = 0; $pending = 0;
    $pasien = [];
 
    foreach ($all as $row) {
        if ($row['status'] === 'done')           { $done++; $sukses++; }
        elseif ($row['status'] === 'skip')       { $done++; $skip++; }
        elseif ($row['status'] === 'error')      { $done++; $error++; }
        elseif ($row['status'] === 'processing') { $done++; }
        else $pending++;
 
        $pasien[] = [
            'no_rawat'    => $row['no_rawat'],
            'nm_pasien'   => $row['nm_pasien'],
            'status'      => $row['status'],
            'error_msg'   => $row['error_msg'],
            'detail_json' => $row['detail_json'] ? json_decode($row['detail_json'], true) : [],
        ];
    }
 
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
    echo json_encode([
        'success' => true, 'periode' => $periode,
        'total'   => $total, 'done' => $done, 'pending' => $pending,
        'sukses'  => $sukses, 'skip' => $skip, 'error' => $error,
        'pct'     => $total > 0 ? round(($done / $total) * 100) : 0,
        'pasien'  => $pasien,
    ]);
    exit();
}
 
// ================================================================
// postResetBunban — Hapus job queue periode tertentu
// POST /satu_sehat/resetbunban
// ================================================================
public function postResetBunban()
{
    ob_start();
    $periode = $_POST['periode'] ?? date('Y-m-d');
    $this->db('satu_sehat_job_queue')->where('periode', $periode)->delete();
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
    echo json_encode(['success' => true, 'message' => 'Job queue direset untuk ' . $periode]);
    exit();
}

  public function getCondition($no_rawat, $render = true)
  {
    $zonawaktu = '+07:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WITA') {
      $zonawaktu = '+08:00';
    }
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WIT') {
      $zonawaktu = '+09:00';
    }

    $no_rawat = revertNoRawat($no_rawat);
    $kd_poli = $this->core->getRegPeriksaInfo('kd_poli', $no_rawat);
    $nm_poli = $this->core->getPoliklinikInfo('nm_poli', $kd_poli);
    $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $no_ktp_dokter = $this->core->getPegawaiInfo('no_ktp', $kd_dokter);
    $nama_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
    $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
    $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
    $nama_pasien = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
    $status_lanjut = $this->core->getRegPeriksaInfo('status_lanjut', $no_rawat);
    $tgl_registrasi = $this->core->getRegPeriksaInfo('tgl_registrasi', $no_rawat);
    $jam_reg = $this->core->getRegPeriksaInfo('jam_reg', $no_rawat);
    $diagnosa_pasien = $this->db('diagnosa_pasien')
      ->join('penyakit', 'penyakit.kd_penyakit=diagnosa_pasien.kd_penyakit')
      ->where('no_rawat', $no_rawat)
      ->where('diagnosa_pasien.status', $status_lanjut)
      ->where('prioritas', '1')
      ->oneArray();

    $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();

    $kunjungan = 'Kunjungan';
    if ($status_lanjut == 'Ranap') {
      $kunjungan = 'Perawatan';
    }

    $__patientResp = $this->getPatient($no_ktp_pasien);
    $__patientJson = json_decode($__patientResp);
    $ihs_patient = '';
    if (is_object($__patientJson) && isset($__patientJson->entry) && is_array($__patientJson->entry) && isset($__patientJson->entry[0]) && isset($__patientJson->entry[0]->resource) && isset($__patientJson->entry[0]->resource->id)) {
      $ihs_patient = $__patientJson->entry[0]->resource->id;
    }

    $kd_penyakit = $diagnosa_pasien['kd_penyakit'] ?? null;
    $nm_penyakit = $diagnosa_pasien['nm_penyakit'] ?? null;
    $encounter_id = $mlite_satu_sehat_response['id_encounter'] ?? null;

    if ($ihs_patient === '' || !$kd_penyakit || !$nm_penyakit || !$encounter_id) {
      $error = [
        'error' => 'Data tidak lengkap untuk Condition',
        'missing' => [
          'patient_id' => $ihs_patient === '' ? 'missing' : 'ok',
          'kd_penyakit' => !$kd_penyakit ? 'missing' : 'ok',
          'nm_penyakit' => !$nm_penyakit ? 'missing' : 'ok',
          'id_encounter' => !$encounter_id ? 'missing' : 'ok'
        ]
      ];
      $response = json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      if ($render) {
        echo $this->draw('condition.html', ['pesan' => 'Gagal mengirim condition platform Satu Sehat!!', 'response' => $response]);
      } else {
        echo $response;
      }
      exit();
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Condition',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . json_decode($this->getToken())->access_token),
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => '{
       "resourceType": "Condition",
       "clinicalStatus": {
          "coding": [
             {
                "system": "http://terminology.hl7.org/CodeSystem/condition-clinical",
                "code": "active",
                "display": "Active"
             }
          ]
       },
       "category": [
          {
             "coding": [
                {
                   "system": "http://terminology.hl7.org/CodeSystem/condition-category",
                   "code": "encounter-diagnosis",
                   "display": "Encounter Diagnosis"
                }
             ]
          }
       ],
       "code": {
          "coding": [
             {
                "system": "http://hl7.org/fhir/sid/icd-10",
                "code": "' . $kd_penyakit . '",
                "display": "' . $nm_penyakit . '"
             }
          ]
       },
       "subject": {
          "reference": "Patient/' . $ihs_patient . '",
          "display": "' . $nama_pasien . '"
       },
       "encounter": {
          "reference": "Encounter/' . $encounter_id . '",
          "display": "' . $kunjungan . ' ' . $nama_pasien . ' dari tanggal ' . $tgl_registrasi . '"
       }
    }',
    ));

    $response = curl_exec($curl);


    $decoded = json_decode($response);
    $id_condition = (is_object($decoded) && isset($decoded->id)) ? $decoded->id : null;
    $pesan = 'Gagal mengirim condition platform Satu Sehat!!';
    if ($id_condition) {
      $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();
      if ($mlite_satu_sehat_response) {
        $this->db('mlite_satu_sehat_response')
          ->where('no_rawat', $no_rawat)
          ->save([
            'no_rawat' => $no_rawat,
            'id_condition' => $id_condition
          ]);
      } else {
        $this->db('mlite_satu_sehat_response')
          ->save([
            'no_rawat' => $no_rawat,
            'id_condition' => $id_condition
          ]);
      }
      $pesan = 'Sukses mengirim condition platform Satu Sehat!!';
    }

    curl_close($curl);
    if ($render) {
      echo $this->draw('condition.html', ['pesan' => $pesan, 'response' => $response]);
    } else {
      $data = json_decode($response);
      echo $data ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $response;
    }
    exit();
  }

  public function getObservation($no_rawat, $ttv, $render = true)
  {

    $zonawaktu = '+07:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WITA') {
      $zonawaktu = '+08:00';
    }
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WIT') {
      $zonawaktu = '+09:00';
    }


    $no_rawat = revertNoRawat($no_rawat);
    $kd_poli = $this->core->getRegPeriksaInfo('kd_poli', $no_rawat);
    $nm_poli = $this->core->getPoliklinikInfo('nm_poli', $kd_poli);
    $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $no_ktp_dokter = $this->core->getPegawaiInfo('no_ktp', $kd_dokter);
    $nama_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
    $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
    $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
    $nama_pasien = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
    $status_lanjut = $this->core->getRegPeriksaInfo('status_lanjut', $no_rawat);
    $tgl_registrasi = $this->core->getRegPeriksaInfo('tgl_registrasi', $no_rawat);
    $jam_reg = $this->core->getRegPeriksaInfo('jam_reg', $no_rawat);

    $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();
    $__patientResp = $this->getPatient($no_ktp_pasien);
    $__patientJson = json_decode($__patientResp);
    $ihs_patient = '';
    if (is_object($__patientJson) && isset($__patientJson->entry) && is_array($__patientJson->entry) && isset($__patientJson->entry[0]) && isset($__patientJson->entry[0]->resource) && isset($__patientJson->entry[0]->resource->id)) {
      $ihs_patient = $__patientJson->entry[0]->resource->id;
    }
    $__pracResp = $this->getPractitioner($no_ktp_dokter);
    $__pracJson = json_decode($__pracResp);
    $practitioner_id = '';
    if (is_object($__pracJson) && isset($__pracJson->entry) && is_array($__pracJson->entry) && isset($__pracJson->entry[0]) && isset($__pracJson->entry[0]->resource) && isset($__pracJson->entry[0]->resource->id)) {
      $practitioner_id = $__pracJson->entry[0]->resource->id;
    }
    $encounter_id = $mlite_satu_sehat_response['id_encounter'] ?? null;

    $pemeriksaan = $this->db('pemeriksaan_ralan')
      ->where('no_rawat', $no_rawat)
      ->limit(1)
      ->desc('tgl_perawatan')
      ->oneArray();

    if ($status_lanjut == 'Ranap') {
      $pemeriksaan = $this->db('pemeriksaan_ranap')
        ->where('no_rawat', $no_rawat)
        ->limit(1)
        ->desc('tgl_perawatan')
        ->oneArray();
    }

    $ttv_hl7_code = '';
    $ttv_hl7_display = '';
    $ttv_loinc_code = '';
    $ttv_loinc_display = '';
    $ttv_unitsofmeasure_value = '';
    $ttv_unitsofmeasure_unit = '';
    $ttv_unitsofmeasure_code = '';

    if ($ttv == 'nadi') {
      $ttv_hl7_code = 'vital-signs';
      $ttv_hl7_display = 'Vital Signs';
      $ttv_loinc_code = '8867-4';
      $ttv_loinc_display = 'Heart rate';
      $val = isset($pemeriksaan['nadi']) ? trim((string) $pemeriksaan['nadi']) : '';
      if ($val === '') {
        $response = json_encode(['error' => 'Data tidak lengkap untuk Observation', 'missing' => ['nadi' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($render) {
          echo $this->draw('observation.html', ['pesan' => 'Gagal mengirim observation ttv ' . $ttv . ' ke platform Satu Sehat!!', 'response' => $response]);
        } else {
          echo $response;
        }
        exit();
      }
      $ttv_unitsofmeasure_value = $val;
      $ttv_unitsofmeasure_unit = 'beats/minute';
      $ttv_unitsofmeasure_code = '/min';
    }

    if ($ttv == 'respirasi') {
      $ttv_hl7_code = 'vital-signs';
      $ttv_hl7_display = 'Vital Signs';
      $ttv_loinc_code = '9279-1';
      $ttv_loinc_display = 'Respiratory rate';
      $val = isset($pemeriksaan['respirasi']) ? trim((string) $pemeriksaan['respirasi']) : '';
      if ($val === '') {
        $response = json_encode(['error' => 'Data tidak lengkap untuk Observation', 'missing' => ['respirasi' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($render) {
          echo $this->draw('observation.html', ['pesan' => 'Gagal mengirim observation ttv ' . $ttv . ' ke platform Satu Sehat!!', 'response' => $response]);
        } else {
          echo $response;
        }
        exit();
      }
      $ttv_unitsofmeasure_value = $val;
      $ttv_unitsofmeasure_unit = 'breaths/minute';
      $ttv_unitsofmeasure_code = '/min';
    }

    if ($ttv == 'suhu') {
      $ttv_hl7_code = 'vital-signs';
      $ttv_hl7_display = 'Vital Signs';
      $ttv_loinc_code = '8310-5';
      $ttv_loinc_display = 'Body temperature';
      $val = isset($pemeriksaan['suhu_tubuh']) ? trim((string) $pemeriksaan['suhu_tubuh']) : '';
      if ($val === '') {
        $response = json_encode(['error' => 'Data tidak lengkap untuk Observation', 'missing' => ['suhu_tubuh' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($render) {
          echo $this->draw('observation.html', ['pesan' => 'Gagal mengirim observation ttv ' . $ttv . ' ke platform Satu Sehat!!', 'response' => $response]);
        } else {
          echo $response;
        }
        exit();
      }
      $ttv_unitsofmeasure_value = $val;
      $ttv_unitsofmeasure_unit = 'degree Celsius';
      $ttv_unitsofmeasure_code = 'Cel';
    }

    if ($ttv == 'spo2') {
      $ttv_hl7_code = 'vital-signs';
      $ttv_hl7_display = 'Vital Signs';
      $ttv_loinc_code = '59408-5';
      $ttv_loinc_display = 'Oxygen saturation';
      $val = isset($pemeriksaan['spo2']) ? trim((string) $pemeriksaan['spo2']) : '';
      if ($val === '') {
        $response = json_encode(['error' => 'Data tidak lengkap untuk Observation', 'missing' => ['spo2' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($render) {
          echo $this->draw('observation.html', ['pesan' => 'Gagal mengirim observation ttv ' . $ttv . ' ke platform Satu Sehat!!', 'response' => $response]);
        } else {
          echo $response;
        }
        exit();
      }
      $ttv_unitsofmeasure_value = $val;
      $ttv_unitsofmeasure_unit = 'percent saturation';
      $ttv_unitsofmeasure_code = '%';
    }

    if ($ttv == 'gcs') {
      $ttv_hl7_code = 'vital-signs';
      $ttv_hl7_display = 'Vital Signs';
      $ttv_loinc_code = '9269-2';
      $ttv_loinc_display = 'Glasgow coma score total';
      $val = isset($pemeriksaan['gcs']) ? trim((string) $pemeriksaan['gcs']) : '';
      if ($val === '') {
        $response = json_encode(['error' => 'Data tidak lengkap untuk Observation', 'missing' => ['gcs' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($render) {
          echo $this->draw('observation.html', ['pesan' => 'Gagal mengirim observation ttv ' . $ttv . ' ke platform Satu Sehat!!', 'response' => $response]);
        } else {
          echo $response;
        }
        exit();
      }
      $ttv_unitsofmeasure_value = $val;
      $ttv_unitsofmeasure_code = '{score}';
    }

    if ($ttv == 'tinggi') {
      $ttv_hl7_code = 'vital-signs';
      $ttv_hl7_display = 'Vital Signs';
      $ttv_loinc_code = '8302-2';
      $ttv_loinc_display = 'Body height';
      $val = isset($pemeriksaan['tinggi']) ? trim((string) $pemeriksaan['tinggi']) : '';
      if ($val === '') {
        $response = json_encode(['error' => 'Data tidak lengkap untuk Observation', 'missing' => ['tinggi' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($render) {
          echo $this->draw('observation.html', ['pesan' => 'Gagal mengirim observation ttv ' . $ttv . ' ke platform Satu Sehat!!', 'response' => $response]);
        } else {
          echo $response;
        }
        exit();
      }
      $ttv_unitsofmeasure_value = $val;
      $ttv_unitsofmeasure_unit = 'centimeter';
      $ttv_unitsofmeasure_code = 'cm';
    }

    if ($ttv == 'berat') {
      $ttv_hl7_code = 'vital-signs';
      $ttv_hl7_display = 'Vital Signs';
      $ttv_loinc_code = '29463-7';
      $ttv_loinc_display = 'Body weight';
      $val = isset($pemeriksaan['berat']) ? trim((string) $pemeriksaan['berat']) : '';
      if ($val === '') {
        $response = json_encode(['error' => 'Data tidak lengkap untuk Observation', 'missing' => ['berat' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($render) {
          echo $this->draw('observation.html', ['pesan' => 'Gagal mengirim observation ttv ' . $ttv . ' ke platform Satu Sehat!!', 'response' => $response]);
        } else {
          echo $response;
        }
        exit();
      }
      $ttv_unitsofmeasure_value = $val;
      $ttv_unitsofmeasure_unit = 'kilogram';
      $ttv_unitsofmeasure_code = 'kg';
    }

    if ($ttv == 'perut') {
      $ttv_hl7_code = 'vital-signs';
      $ttv_hl7_display = 'Vital Signs';
      $ttv_loinc_code = '8280-0';
      $ttv_loinc_display = 'Waist Circumference at umbilicus by Tape measure';
      $val = isset($pemeriksaan['lingkar_perut']) ? trim((string) $pemeriksaan['lingkar_perut']) : '';
      if ($val === '') {
        $response = json_encode(['error' => 'Data tidak lengkap untuk Observation', 'missing' => ['lingkar_perut' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($render) {
          echo $this->draw('observation.html', ['pesan' => 'Gagal mengirim observation ttv ' . $ttv . ' ke platform Satu Sehat!!', 'response' => $response]);
        } else {
          echo $response;
        }
        exit();
      }
      $ttv_unitsofmeasure_value = $val;
      $ttv_unitsofmeasure_unit = 'centimeter';
      $ttv_unitsofmeasure_code = 'cm';
    }

    if ($ttv == 'tensi') {
      $ttv_hl7_code = 'vital-signs';
      $ttv_hl7_display = 'Vital Signs';
      $ttv_loinc_code = '35094-2';
      $ttv_loinc_display = 'Blood pressure panel';
      $bp = isset($pemeriksaan['tensi']) ? trim((string) $pemeriksaan['tensi']) : '';
      if ($bp === '' || strpos($bp, '/') === false) {
        $error = [
          'error' => 'Data tidak lengkap untuk Observation',
          'missing' => [
            'tensi' => 'missing'
          ]
        ];
        $response = json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($render) {
          echo $this->draw('observation.html', ['pesan' => 'Gagal mengirim observation ttv ' . $ttv . ' ke platform Satu Sehat!!', 'response' => $response]);
        } else {
          echo $response;
        }
        exit();
      }
      $sistole = strtok($bp, '/');
      $diastole = substr($bp, strpos($bp, '/') + 1);
      $ttv_unitsofmeasure_unit = 'mmHg';
      $ttv_unitsofmeasure_code = 'mm[Hg]';
    }

    if ($ttv == 'kesadaran') {
      $ttv_hl7_code = 'exam';
      $ttv_hl7_display = 'Exam';
      $val = isset($pemeriksaan['kesadaran']) ? trim((string) $pemeriksaan['kesadaran']) : '';
      if ($val === '') {
        $response = json_encode(['error' => 'Data tidak lengkap untuk Observation', 'missing' => ['kesadaran' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($render) {
          echo $this->draw('observation.html', ['pesan' => 'Gagal mengirim observation ttv ' . $ttv . ' ke platform Satu Sehat!!', 'response' => $response]);
        } else {
          echo $response;
        }
        exit();
      }
      $ttv_unitsofmeasure_value = $val;
      if ($val === 'Somnolence') {
        $ttv_unitsofmeasure_value = 'Voice';
      } elseif ($val === 'Sopor') {
        $ttv_unitsofmeasure_value = 'Pain';
      } elseif ($val === 'Compos Mentis') {
        $ttv_unitsofmeasure_value = 'Alert';
      }
    }

    if ($ihs_patient === '' || !$encounter_id || $practitioner_id === '') {
      $error = [
        'error' => 'Data tidak lengkap untuk Observation',
        'missing' => [
          'patient_id' => $ihs_patient === '' ? 'missing' : 'ok',
          'id_encounter' => !$encounter_id ? 'missing' : 'ok',
          'practitioner_id' => $practitioner_id === '' ? 'missing' : 'ok'
        ]
      ];
      $response = json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      if ($render) {
        echo $this->draw('observation.html', ['pesan' => 'Gagal mengirim observation ttv ' . $ttv . ' ke platform Satu Sehat!!', 'response' => $response]);
      } else {
        echo $response;
      }
      exit();
    }

    $curl = curl_init();

    if ($ttv == 'kesadaran') {
      curl_setopt_array($curl, array(
        CURLOPT_URL => $this->fhirurl . '/Observation',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => '{
          "resourceType": "Observation",
          "status": "final",
          "category": [
              {
                  "coding": [
                      {
                          "system": "http://terminology.hl7.org/CodeSystem/observation-category",
                          "code": "' . $ttv_hl7_code . '",
                          "display": "' . $ttv_hl7_display . '"
                      }
                  ]
              }
          ],
          "code": {
              "coding": [
                  {
                      "system": "http://snomed.info/sct",
                      "code": "1104441000000107",
                      "display": "ACVPU (Alert Confusion Voice Pain Unresponsive) scale score"
                  }
              ]
          },
          "subject": {
              "reference": "Patient/' . $ihs_patient . '"
          },
          "performer": [
              {
                  "reference": "Practitioner/' . $practitioner_id . '"
              }
          ],
          "encounter": {
              "reference": "Encounter/' . $encounter_id . '",
              "display": "Pemeriksaan fisik ' . $ttv . ' ' . $nama_pasien . ' tanggal ' . $tgl_registrasi . '"
          },
          "effectiveDateTime": "' . $pemeriksaan['tgl_perawatan'] . 'T' . $pemeriksaan['jam_rawat'] . '' . $zonawaktu . '",
          "issued": "' . $pemeriksaan['tgl_perawatan'] . 'T' . $pemeriksaan['jam_rawat'] . '' . $zonawaktu . '",
          "valueCodeableConcept": {
              "text": "' . $ttv_unitsofmeasure_value . '"
          }
        }',
      ));
    } elseif ($ttv == 'tensi') {
      curl_setopt_array($curl, array(
        CURLOPT_URL => $this->fhirurl . '/Observation',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => '{
          "resourceType": "Observation",
          "status": "final",
          "category": [
              {
                  "coding": [
                      {
                          "system": "http://terminology.hl7.org/CodeSystem/observation-category",
                          "code": "' . $ttv_hl7_code . '",
                          "display": "' . $ttv_hl7_display . '"
                      }
                  ]
              }
          ],
          "code": {
            "coding": [
                {
                    "system": "http://loinc.org",
                    "code": "' . $ttv_loinc_code . '",
                    "display": "' . $ttv_loinc_display . '"
                }
            ],
            "text": "Blood pressure systolic & diastolic"
          },
          "subject": {
              "reference": "Patient/' . $ihs_patient . '"
          },
          "performer": [
              {
                  "reference": "Practitioner/' . $practitioner_id . '"
              }
          ],
          "encounter": {
              "reference": "Encounter/' . $encounter_id . '",
              "display": "Pemeriksaan fisik ' . $ttv . ' ' . $nama_pasien . ' tanggal ' . $tgl_registrasi . '"
          },
          "effectiveDateTime": "' . $pemeriksaan['tgl_perawatan'] . 'T' . $pemeriksaan['jam_rawat'] . '' . $zonawaktu . '",
          "issued": "' . $pemeriksaan['tgl_perawatan'] . 'T' . $pemeriksaan['jam_rawat'] . '' . $zonawaktu . '",
          "component": [
            {
              "code": {
                "coding": [
                    {
                        "system": "http://loinc.org",
                        "code": "8480-6",
                        "display": "Systolic blood pressure"
                    }
                ]
              },
              "valueQuantity": {
                "value": ' . intval($sistole) . ',
                "unit": "' . $ttv_unitsofmeasure_unit . '",
                "system": "http://unitsofmeasure.org",
                "code": "' . $ttv_unitsofmeasure_code . '"
              }  
            }, 
            {
              "code": {
                "coding": [
                    {
                        "system": "http://loinc.org",
                        "code": "8462-4",
                        "display": "Diastolic blood pressure"
                    }
                ]
              },
              "valueQuantity": {
                "value": ' . intval($diastole) . ',
                "unit": "' . $ttv_unitsofmeasure_unit . '",
                "system": "http://unitsofmeasure.org",
                "code": "' . $ttv_unitsofmeasure_code . '"
              } 
            }
          ]
        }',
      ));
    } else {
      curl_setopt_array($curl, array(
        CURLOPT_URL => $this->fhirurl . '/Observation',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => '{
          "resourceType": "Observation",
          "status": "final",
          "category": [
              {
                  "coding": [
                      {
                          "system": "http://terminology.hl7.org/CodeSystem/observation-category",
                          "code": "' . $ttv_hl7_code . '",
                          "display": "' . $ttv_hl7_display . '"
                      }
                  ]
              }
          ],
          "code": {
              "coding": [
                  {
                      "system": "http://loinc.org",
                      "code": "' . $ttv_loinc_code . '",
                      "display": "' . $ttv_loinc_display . '"
                  }
              ]
          },
          "subject": {
              "reference": "Patient/' . $ihs_patient . '"
          },
          "performer": [
              {
                  "reference": "Practitioner/' . $practitioner_id . '"
              }
          ],
          "encounter": {
              "reference": "Encounter/' . $encounter_id . '",
              "display": "Pemeriksaan fisik ' . $ttv . ' ' . $nama_pasien . ' tanggal ' . $tgl_registrasi . '"
          },
          "effectiveDateTime": "' . $pemeriksaan['tgl_perawatan'] . 'T' . $pemeriksaan['jam_rawat'] . '' . $zonawaktu . '",
          "issued": "' . $pemeriksaan['tgl_perawatan'] . 'T' . $pemeriksaan['jam_rawat'] . '' . $zonawaktu . '",
          "valueQuantity": {
              "value": ' . intval($ttv_unitsofmeasure_value) . ',
              "unit": "' . $ttv_unitsofmeasure_unit . '",
              "system": "http://unitsofmeasure.org",
              "code": "' . $ttv_unitsofmeasure_code . '"
          }
        }',
      ));
    }

    $response = curl_exec($curl);


    $decoded = json_decode($response);
    $id_observation = (is_object($decoded) && isset($decoded->id)) ? $decoded->id : null;
    $pesan = 'Gagal mengirim observation ttv ' . $ttv . ' ke platform Satu Sehat!!';
    if ($id_observation) {
      $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();
      if ($mlite_satu_sehat_response) {
        $this->db('mlite_satu_sehat_response')
          ->where('no_rawat', $no_rawat)
          ->save([
            'no_rawat' => $no_rawat,
            'id_observation_ttv' . $ttv . '' => $id_observation
          ]);
        $pesan = 'Sukses mengirim observation ttv ' . $ttv . ' ke platform Satu Sehat!!';
      }
    }

    curl_close($curl);
    if ($render) {
      echo $this->draw('observation.html', ['pesan' => $pesan, 'response' => $response]);
    } else {
      $data = json_decode($response);
      echo $data ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $response;
    }
    exit();
  }

  public function getMappingLab()
  {
    $this->_addHeaderFiles();
    $mapping_lab = $this->db('mlite_satu_sehat_mapping_lab')
      ->join('template_laboratorium', 'template_laboratorium.id_template = mlite_satu_sehat_mapping_lab.id_template')
      ->toArray();
    $template_laboratorium = $this->db('template_laboratorium')->toArray();
    return $this->draw('mapping.lab.html', ['mapping_lab_satu_sehat' => $mapping_lab, 'template_laboratorium' => $template_laboratorium]);
  }

  public function getMappingRad()
  {
    $this->_addHeaderFiles();
    $mapping_rad = $this->db('satu_sehat_mapping_radiologi')
        ->join('jns_perawatan_radiologi', 'jns_perawatan_radiologi.kd_jenis_prw = satu_sehat_mapping_radiologi.kd_jenis_prw')
        ->toArray();
    $jns_perawatan_radiologi = $this->db('jns_perawatan_radiologi')->toArray();
    return $this->draw('mapping.rad.html', [
        'mapping_rad_satu_sehat'  => $mapping_rad,
        'jns_perawatan_radiologi' => $jns_perawatan_radiologi
    ]);
  }

  public function getRadiology($no_rawat = '', $tipe = '', $render = true)
  {
    $zonawaktu = '+07:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WITA') $zonawaktu = '+08:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WIT')  $zonawaktu = '+09:00';

    $no_rawat = revertNoRawat($no_rawat);
    $pesan    = '';
    $response = '';
    
    $no_rkm_medis  = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
    $nm_pasien     = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
    $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
    $tgl_lahir     = $this->core->getPasienInfo('tgl_lahir', $no_rkm_medis);
    $jk            = $this->core->getPasienInfo('jk', $no_rkm_medis);
    $kd_dokter     = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $nm_dokter     = $this->core->getPegawaiInfo('nama', $kd_dokter);
    $id_dokter     = $this->db('mlite_satu_sehat_mapping_praktisi')
        ->select('practitioner_id')->where('kd_dokter', $kd_dokter)->oneArray();

    $__patientResp = $this->getPatient($no_ktp_pasien);
    $__patientJson = json_decode($__patientResp);
    $id_pasien = '';
    if (is_object($__patientJson) && isset($__patientJson->entry) && is_array($__patientJson->entry) && isset($__patientJson->entry[0]->resource->id)) {
        $id_pasien = $__patientJson->entry[0]->resource->id;
    }

    $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();

    $permintaan_radiologi = $this->db('permintaan_radiologi')->where('no_rawat', $no_rawat)->oneArray();
    $permintaan_pemeriksaan_radiologi = [];
    $mapping_radiologi = [];

    if (!empty($permintaan_radiologi['noorder'])) {
        $permintaan_pemeriksaan_radiologi = $this->db('permintaan_pemeriksaan_radiologi')
            ->join('jns_perawatan_radiologi', 'jns_perawatan_radiologi.kd_jenis_prw = permintaan_pemeriksaan_radiologi.kd_jenis_prw')
            ->where('noorder', $permintaan_radiologi['noorder'])->oneArray();

        if (!empty($permintaan_pemeriksaan_radiologi['kd_jenis_prw'])) {
            $mapping_radiologi = $this->db('satu_sehat_mapping_radiologi')
                ->where('kd_jenis_prw', $permintaan_pemeriksaan_radiologi['kd_jenis_prw'])->oneArray();
        }
    }

    $dep_rad    = $this->db('satu_sehat_mapping_departemen')
        ->where('dep_id', $this->core->getSettings('satu_sehat', 'radiologi'))->oneArray();
    $lokasi_rad = $this->db('satu_sehat_mapping_lokasi_ruangrad')
        ->where('id_organisasi_satusehat', $dep_rad['id_organisasi_satusehat'] ?? '')->oneArray();
    $id_org_rad = $lokasi_rad['id_organisasi_satusehat'] ?? '';

    if ($tipe == 'request') {

        if (empty($permintaan_radiologi['noorder'])) {
            echo json_encode(['error' => 'Data tidak lengkap untuk Radiology request', 'missing' => ['permintaan_radiologi.noorder' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }
        if (empty($permintaan_pemeriksaan_radiologi['kd_jenis_prw'])) {
            echo json_encode(['error' => 'Data tidak lengkap untuk Radiology request', 'missing' => ['permintaan_pemeriksaan_radiologi.kd_jenis_prw' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }
        if ($id_pasien === '') {
            echo json_encode(['error' => 'Data tidak lengkap untuk Radiology request', 'missing' => ['patient_id' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }

        $radiologi = '{
            "resourceType": "ServiceRequest",
            "identifier": [{"system": "http://sys-ids.kemkes.go.id/acsn/' . $this->organizationid . '", "value": "' . $permintaan_radiologi['noorder'] . '"}],
            "status": "active",
            "intent": "order",
            "priority": "routine",
            "category": [{"coding": [{"system": "http://snomed.info/sct", "code": "363679005", "display": "Imaging"}]}],
            "code": {
                "coding": [{"system": "' . ($mapping_radiologi['system'] ?? 'http://loinc.org') . '", "code": "' . ($mapping_radiologi['code'] ?? '') . '", "display": "' . ($mapping_radiologi['display'] ?? '') . '"}],
                "text": "' . ($permintaan_pemeriksaan_radiologi['nm_perawatan'] ?? '') . '"
            },
            "subject": {"reference": "Patient/' . $id_pasien . '", "display": "' . $nm_pasien . '"},
            "encounter": {
                "reference": "Encounter/' . ($mlite_satu_sehat_response['id_encounter'] ?? '') . '",
                "display": "Permintaan ' . ($permintaan_pemeriksaan_radiologi['nm_perawatan'] ?? '') . ' Atas nama ' . $nm_pasien . ' No.RM ' . $no_rkm_medis . ' No. Rawat ' . $no_rawat . ' pada tanggal ' . ($permintaan_radiologi['tgl_permintaan'] ?? '') . ' jam ' . ($permintaan_radiologi['jam_permintaan'] ?? '') . '"
            },
            "occurrenceDateTime": "' . ($permintaan_radiologi['tgl_permintaan'] ?? '') . 'T' . ($permintaan_radiologi['jam_permintaan'] ?? '') . $zonawaktu . '",
            "requester": {"reference": "Practitioner/' . ($id_dokter['practitioner_id'] ?? '') . '", "display": "' . $nm_dokter . '"},
            "performer": [{"reference": "Organization/' . $id_org_rad . '"}],
            "reasonCode": [{"text": "Permintaan pemeriksaan radiologi dengan Accession Number ' . ($permintaan_radiologi['noorder'] ?? '') . ' dan diagnosa klinis: ' . ($permintaan_radiologi['diagnosa_klinis'] ?? '') . '"}]
        }';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->fhirurl . '/ServiceRequest',
            CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . json_decode($this->getToken())->access_token],
            CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => $radiologi,
        ]);
        $response             = curl_exec($curl);
        $id_radiologi_request = isset_or(json_decode($response)->id, '');
        $pesan                = 'Gagal mengirim radiologi request platform Satu Sehat!!';
        if ($id_radiologi_request) {
            $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->save(['id_rad_request' => $id_radiologi_request]);
            $pesan = 'Sukses mengirim radiologi request platform Satu Sehat!!';
        }
        curl_close($curl);

    } elseif ($tipe == 'specimen') {

        if (empty($permintaan_radiologi['noorder'])) {
            echo json_encode(['error' => 'Data tidak lengkap untuk Radiology specimen', 'missing' => ['permintaan_radiologi.noorder' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }
        if ($id_pasien === '') {
            echo json_encode(['error' => 'Data tidak lengkap untuk Radiology specimen', 'missing' => ['patient_id' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }

        $radiologi = '{
            "resourceType": "Specimen",
            "identifier": [{"system": "http://sys-ids.kemkes.go.id/specimen/' . $this->organizationid . '", "value": "' . ($permintaan_radiologi['noorder'] ?? '') . '"}],
            "status": "available",
            "type": {
                "coding": [{"system": "' . ($mapping_radiologi['sampel_system'] ?? 'http://snomed.info/sct') . '", "code": "' . ($mapping_radiologi['sampel_code'] ?? '') . '", "display": "' . ($mapping_radiologi['sampel_display'] ?? '') . '"}]
            },
            "subject": {"reference": "Patient/' . $id_pasien . '", "display": "' . $nm_pasien . '"},
            "receivedTime": "' . ($permintaan_radiologi['tgl_permintaan'] ?? '') . 'T' . ($permintaan_radiologi['jam_permintaan'] ?? '') . $zonawaktu . '",
            "request": [{"reference": "ServiceRequest/' . ($mlite_satu_sehat_response['id_rad_request'] ?? '') . '"}]
        }';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->fhirurl . '/Specimen',
            CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . json_decode($this->getToken())->access_token],
            CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => $radiologi,
        ]);
        $response              = curl_exec($curl);
        $id_radiologi_specimen = isset_or(json_decode($response)->id, '');
        $pesan                 = 'Gagal mengirim radiologi specimen platform Satu Sehat!!';
        if ($id_radiologi_specimen) {
            $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->save(['id_rad_specimen' => $id_radiologi_specimen]);
            $pesan = 'Sukses mengirim radiologi specimen platform Satu Sehat!!';
        }
        curl_close($curl);

    } elseif ($tipe == 'observation') {

        if (empty($permintaan_radiologi['noorder'])) {
            echo json_encode(['error' => 'Data tidak lengkap untuk Radiology result', 'missing' => ['permintaan_radiologi.noorder' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }
        if ($id_pasien === '') {
            echo json_encode(['error' => 'Data tidak lengkap untuk Radiology observation', 'missing' => ['patient_id' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }

        $hasil_radiologi = $this->db('hasil_radiologi')->where('no_rawat', $no_rawat)->oneArray();

        $radiologi = '{
            "resourceType": "Observation",
            "identifier": [{"system": "http://sys-ids.kemkes.go.id/observation/' . $this->organizationid . '", "value": "' . ($permintaan_radiologi['noorder'] ?? '') . '"}],
            "status": "final",
            "category": [{"coding": [{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "imaging", "display": "Imaging"}]}],
            "code": {
                "coding": [{"system": "' . ($mapping_radiologi['system'] ?? 'http://loinc.org') . '", "code": "' . ($mapping_radiologi['code'] ?? '') . '", "display": "' . ($mapping_radiologi['display'] ?? '') . '"}]
            },
            "subject": {"reference": "Patient/' . $id_pasien . '", "display": "' . $nm_pasien . '"},
            "encounter": {"reference": "Encounter/' . ($mlite_satu_sehat_response['id_encounter'] ?? '') . '"},
            "effectiveDateTime": "' . ($permintaan_radiologi['tgl_hasil'] ?? '') . 'T' . ($permintaan_radiologi['jam_hasil'] ?? '') . $zonawaktu . '",
            "performer": [{"reference": "Practitioner/' . ($id_dokter['practitioner_id'] ?? '') . '", "display": "' . $nm_dokter . '"}],
            "valueString": ' . json_encode($hasil_radiologi['hasil'] ?? '') . '
        }';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->fhirurl . '/Observation',
            CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . json_decode($this->getToken())->access_token],
            CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => $radiologi,
        ]);
        $response                 = curl_exec($curl);
        $id_radiologi_observation = isset_or(json_decode($response)->id, '');
        $pesan                    = 'Gagal mengirim radiologi observation platform Satu Sehat!!';
        if ($id_radiologi_observation) {
            $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->save(['id_rad_observation' => $id_radiologi_observation]);
            $pesan = 'Sukses mengirim radiologi observation platform Satu Sehat!!';
        }
        curl_close($curl);

    } elseif ($tipe == 'diagnostic') {

        if (empty($permintaan_radiologi['noorder'])) {
            echo json_encode(['error' => 'Data tidak lengkap untuk Radiology result', 'missing' => ['permintaan_radiologi.noorder' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }
        if ($id_pasien === '') {
            echo json_encode(['error' => 'Data tidak lengkap untuk Radiology diagnostic report', 'missing' => ['patient_id' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }

        $radiologi = '{
            "resourceType": "DiagnosticReport",
            "identifier": [{"system": "http://sys-ids.kemkes.go.id/diagnostic/' . $this->organizationid . '/rad", "value": "' . ($permintaan_radiologi['noorder'] ?? '') . '"}],
            "status": "final",
            "category": [{"coding": [{"system": "http://terminology.hl7.org/CodeSystem/v2-0074", "code": "RAD", "display": "Radiology"}]}],
            "code": {
                "coding": [{"system": "' . ($mapping_radiologi['system'] ?? 'http://loinc.org') . '", "code": "' . ($mapping_radiologi['code'] ?? '') . '", "display": "' . ($mapping_radiologi['display'] ?? '') . '"}]
            },
            "subject": {"reference": "Patient/' . $id_pasien . '", "display": "' . $nm_pasien . '"},
            "encounter": {"reference": "Encounter/' . ($mlite_satu_sehat_response['id_encounter'] ?? '') . '"},
            "effectiveDateTime": "' . ($permintaan_radiologi['tgl_hasil'] ?? '') . 'T' . ($permintaan_radiologi['jam_hasil'] ?? '') . $zonawaktu . '",
            "issued": "' . ($permintaan_radiologi['tgl_hasil'] ?? '') . 'T' . ($permintaan_radiologi['jam_hasil'] ?? '') . $zonawaktu . '",
            "performer": [{"reference": "Practitioner/' . ($id_dokter['practitioner_id'] ?? '') . '", "display": "' . $nm_dokter . '"}],
            "specimen": [{"reference": "Specimen/' . ($mlite_satu_sehat_response['id_rad_specimen'] ?? '') . '"}],
            "result": [{"reference": "Observation/' . ($mlite_satu_sehat_response['id_rad_observation'] ?? '') . '"}],
            "basedOn": [{"reference": "ServiceRequest/' . ($mlite_satu_sehat_response['id_rad_request'] ?? '') . '"}]
        }';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->fhirurl . '/DiagnosticReport',
            CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . json_decode($this->getToken())->access_token],
            CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => $radiologi,
        ]);
        $response                = curl_exec($curl);
        $id_radiologi_diagnostic = isset_or(json_decode($response)->id, '');
        $pesan                   = 'Gagal mengirim radiologi diagnostic platform Satu Sehat!!';
        if ($id_radiologi_diagnostic) {
            $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->save(['id_rad_diagnostic' => $id_radiologi_diagnostic]);
            $pesan = 'Sukses mengirim radiologi diagnostic platform Satu Sehat!!';
        }
        curl_close($curl);

    } elseif ($tipe == 'image') {

        // Safety check: ServiceRequest harus sudah ada
        if (empty($mlite_satu_sehat_response['id_rad_request'])) {
            $pesan    = 'GAGAL: ServiceRequest Radiologi belum dikirim! Kirim dulu Service Request Radiologi sebelum Post Image Study.';
            $response = json_encode(['error' => 'id_rad_request missing'], JSON_PRETTY_PRINT);
            if ($render) echo $this->draw('radiology.html', ['pesan' => $pesan, 'response' => $response]);
            else echo $response;
            exit();
        }

        if (empty($permintaan_radiologi['noorder'])) {
            $pesan    = 'GAGAL: Tidak ada permintaan radiologi (noorder)!';
            $response = json_encode(['error' => 'noorder missing', 'permintaan_radiologi' => $permintaan_radiologi], JSON_PRETTY_PRINT);
            if ($render) echo $this->draw('radiology.html', ['pesan' => $pesan, 'response' => $response]);
            else echo $response;
            exit();
        }

        if ($id_pasien === '') {
            $pesan    = 'GAGAL: Patient ID tidak ditemukan di Satu Sehat!';
            $response = json_encode(['error' => 'patient_id missing'], JSON_PRETTY_PRINT);
            if ($render) echo $this->draw('radiology.html', ['pesan' => $pesan, 'response' => $response]);
            else echo $response;
            exit();
        }

        $noorder = $permintaan_radiologi['noorder'];

        // Ambil gambar dari gambar_radiologi
        $gambar_list = $this->db('gambar_radiologi')->where('no_rawat', $no_rawat)->toArray();
        if (empty($gambar_list)) {
            $pesan    = 'GAGAL: Tidak ada gambar radiologi untuk no_rawat ini!';
            $response = json_encode(['error' => 'no images found'], JSON_PRETTY_PRINT);
            if ($render) echo $this->draw('radiology.html', ['pesan' => $pesan, 'response' => $response]);
            else echo $response;
            exit();
        }

        // Cek/buat Study UID
        $existing_study = $this->db('mlite_mini_pacs_study')->where('no_rawat', $no_rawat)->oneArray();
        if ($existing_study) {
            $study_instance_uid = $existing_study['study_instance_uid'];
            $study_db_id        = $existing_study['id'];
            $study_date         = $existing_study['study_date'];
            $modality           = $existing_study['modality'] ?? 'CR';
        } else {
            $study_instance_uid = $this->generateDicomUid();
            $study_date         = $permintaan_radiologi['tgl_permintaan'] . ' ' . $permintaan_radiologi['jam_permintaan'];
            $modality           = 'CR';
            $study_db_id        = $this->db('mlite_mini_pacs_study')->save([
                'no_rawat'           => $no_rawat,
                'study_instance_uid' => $study_instance_uid,
                'study_date'         => $study_date,
                'modality'           => $modality,
                'description'        => $noorder,
            ]);
        }

        // Buat Series UID
        $series_instance_uid = $this->generateDicomUid();
        $series_db_id = $this->db('mlite_mini_pacs_series')->save([
            'study_id'            => $study_db_id,
            'no_rawat'            => $no_rawat,
            'series_instance_uid' => $series_instance_uid,
            'modality'            => $modality,
            'series_number'       => 1,
        ]);

        $pacs_base = realpath(BASE_DIR) . '/uploads/pacs/' . $noorder . '/';
        if (!is_dir($pacs_base)) mkdir($pacs_base, 0755, true);

        $radiologi_base = realpath(WEBAPPS_PATH . '/radiologi') . '/';

        // Format metadata DICOM
        $sex_dicom        = 'O';
        if ($jk == 'L') $sex_dicom = 'M';
        if ($jk == 'P') $sex_dicom = 'F';
        $tgl_lahir_dicom  = str_replace('-', '', $tgl_lahir ?? '');
        $nm_pasien_dicom  = strtoupper(str_replace([' ', ','], ['^', ''], $nm_pasien));
        $study_date_dicom = date('Ymd', strtotime($study_date));
        $study_time_dicom = date('His', strtotime($study_date));
        $sop_class        = '1.2.840.10008.5.1.4.1.1.1'; // CR SOP Class UID

        $instances       = [];
        $instance_no     = 1;
        $last_cmd_output = '';

        foreach ($gambar_list as $gambar) {
            $src_path = realpath($radiologi_base . $gambar['lokasi_gambar']);
            if (!$src_path || !file_exists($src_path)) continue;

            $sop_instance_uid = $this->generateDicomUid();
            $dcm_filename     = $noorder . '_' . $instance_no . '.dcm';
            $dcm_path         = $pacs_base . $dcm_filename;

            $cmd = 'img2dcm'
                . ' -k "StudyInstanceUID=' . $study_instance_uid . '"'
                . ' -k "SeriesInstanceUID=' . $series_instance_uid . '"'
                . ' -k "SOPInstanceUID=' . $sop_instance_uid . '"'
                . ' -k "SOPClassUID=' . $sop_class . '"'
                . ' -k "PatientName=' . addslashes($nm_pasien_dicom) . '"'
                . ' -k "PatientID=' . $no_rkm_medis . '"'
                . ' -k "PatientBirthDate=' . $tgl_lahir_dicom . '"'
                . ' -k "PatientSex=' . $sex_dicom . '"'
                . ' -k "StudyDate=' . $study_date_dicom . '"'
                . ' -k "StudyTime=' . $study_time_dicom . '"'
                . ' -k "AccessionNumber=' . $noorder . '"'
                . ' -k "Modality=' . $modality . '"'
                . ' -k "InstanceNumber=' . $instance_no . '"'
                . ' ' . escapeshellarg($src_path)
                . ' ' . escapeshellarg($dcm_path)
                . ' 2>&1';

            $last_cmd_output = shell_exec($cmd);

            if (!file_exists($dcm_path)) continue;

            $this->db('mlite_mini_pacs_instance')->save([
                'series_id'        => $series_db_id,
                'no_rawat'         => $no_rawat,
                'sop_instance_uid' => $sop_instance_uid,
                'sop_class_uid'    => $sop_class,
                'file_path'        => 'uploads/pacs/' . $noorder . '/' . $dcm_filename,
            ]);

            $instances[] = [
                'sop_instance_uid' => $sop_instance_uid,
                'sop_class_uid'    => $sop_class,
                'instance_number'  => $instance_no,
            ];

            $instance_no++;
        }

        if (empty($instances)) {
            $pesan    = 'GAGAL: Tidak ada gambar yang berhasil dikonversi ke DICOM! Output img2dcm: ' . $last_cmd_output;
            $response = json_encode(['error' => 'conversion failed', 'img2dcm_output' => $last_cmd_output, 'radiologi_base' => $radiologi_base], JSON_PRETTY_PRINT);
            if ($render) echo $this->draw('radiology.html', ['pesan' => $pesan, 'response' => $response]);
            else echo $response;
            exit();
        }

        $total          = count($instances);
        $instances_json = '';
        foreach ($instances as $i => $inst) {
            $instances_json .= '{
                        "uid": "' . $inst['sop_instance_uid'] . '",
                        "sopClass": {
                            "system": "urn:ietf:rfc:3986",
                            "code": "urn:oid:' . $inst['sop_class_uid'] . '"
                        },
                        "number": ' . $inst['instance_number'] . '
                    }' . ($i < $total - 1 ? ',' : '');
        }

        $imaging_study = '{
            "resourceType": "ImagingStudy",
            "identifier": [
                {
                    "system": "urn:dicom:uid",
                    "value": "urn:oid:' . $study_instance_uid . '"
                },
                {
                    "type": {
                        "coding": [{"system": "http://terminology.hl7.org/CodeSystem/v2-0203", "code": "ACSN"}]
                    },
                    "system": "http://sys-ids.kemkes.go.id/acsn/' . $this->organizationid . '",
                    "value": "' . $noorder . '"
                }
            ],
            "status": "available",
            "modality": [{"system": "http://dicom.nema.org/resources/ontology/DCM", "code": "' . $modality . '"}],
            "subject": {"reference": "Patient/' . $id_pasien . '", "display": "' . $nm_pasien . '"},
            "encounter": {"reference": "Encounter/' . ($mlite_satu_sehat_response['id_encounter'] ?? '') . '"},
            "started": "' . $permintaan_radiologi['tgl_permintaan'] . 'T' . $permintaan_radiologi['jam_permintaan'] . $zonawaktu . '",
            "basedOn": [{"reference": "ServiceRequest/' . ($mlite_satu_sehat_response['id_rad_request'] ?? '') . '"}],
            "referrer": {"reference": "Practitioner/' . ($id_dokter['practitioner_id'] ?? '') . '", "display": "' . $nm_dokter . '"},
            "numberOfSeries": 1,
            "numberOfInstances": ' . $total . ',
            "series": [
                {
                    "uid": "' . $series_instance_uid . '",
                    "number": 1,
                    "modality": {"system": "http://dicom.nema.org/resources/ontology/DCM", "code": "' . $modality . '"},
                    "numberOfInstances": ' . $total . ',
                    "instance": [' . $instances_json . ']
                }
            ]
        }';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->fhirurl . '/ImagingStudy',
            CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT        => 60, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . json_decode($this->getToken())->access_token],
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $imaging_study,
        ]);
        $response         = curl_exec($curl);
        $decoded          = json_decode($response);
        $id_imaging_study = $decoded->id ?? null;
        $pesan            = 'GAGAL mengirim ImagingStudy ke Satu Sehat!!';

        if ($id_imaging_study) {
            $existing = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();
            if ($existing) {
                $this->db('mlite_satu_sehat_response')
                    ->where('no_rawat', $no_rawat)
                    ->save(['id_imaging_study' => $id_imaging_study]);
            } else {
                $this->db('mlite_satu_sehat_response')
                    ->save(['no_rawat' => $no_rawat, 'id_imaging_study' => $id_imaging_study]);
            }
            $pesan = 'Sukses mengirim ImagingStudy ke Satu Sehat!! (' . $total . ' instance) ACSN: ' . $noorder . ' | ID: ' . $id_imaging_study;
        }

        curl_close($curl);
    }

    if ($render) {
        echo $this->draw('radiology.html', ['pesan' => $pesan, 'response' => $response]);
    } else {
        echo $response;
    }
    exit();
  }

  public function getStudiIdByNoOrder($noorder)
  {

    $orthanc_server = $this->settings->get('orthanc.server');
    // $orthanc_server = 'http://172.18.0.7:8042';
    $orthanc_user = $this->settings->get('orthanc.username');
    $orthanc_password = $this->settings->get('orthanc.password');

    $ch = curl_init($orthanc_server . '/tools/find');

    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_USERPWD => $orthanc_user . ':' . $orthanc_password,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
      CURLOPT_POSTFIELDS => json_encode([
        "Level" => "Study",
        "Query" => [
          "AccessionNumber" => $noorder
        ]
      ])
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $studyIds = json_decode($response, true);
    // print_r($studyIds);
    return $studyIds[0] ?? '';
  }

  public function getCarePlan($no_rawat, $render = true)
  {

    $zonawaktu = '+07:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WITA') {
      $zonawaktu = '+08:00';
    }
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WIT') {
      $zonawaktu = '+09:00';
    }


    $no_rawat = revertNoRawat($no_rawat);
    $kd_poli = $this->core->getRegPeriksaInfo('kd_poli', $no_rawat);
    $nm_poli = $this->core->getPoliklinikInfo('nm_poli', $kd_poli);
    $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $no_ktp_dokter = $this->core->getPegawaiInfo('no_ktp', $kd_dokter);
    $nama_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
    $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
    $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
    $nama_pasien = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
    $status_lanjut = $this->core->getRegPeriksaInfo('status_lanjut', $no_rawat);
    $tgl_registrasi = $this->core->getRegPeriksaInfo('tgl_registrasi', $no_rawat);
    $jam_reg = $this->core->getRegPeriksaInfo('jam_reg', $no_rawat);
    $mlite_billing = $this->db('mlite_billing')->where('no_rawat', $no_rawat)->oneArray();
    $pemeriksaan = $this->db('pemeriksaan_ralan')->where('no_rawat', $no_rawat)->oneArray();

    $rtl = $pemeriksaan['rtl'] ?? null;

    $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();

    $id_dokter = $this->db('mlite_satu_sehat_mapping_praktisi')
      ->select('practitioner_id')
      ->where('kd_dokter', $kd_dokter)
      ->oneArray();

    $kunjungan = 'Kunjungan';
    if ($status_lanjut == 'Ranap') {
      $kunjungan = 'Perawatan';
      $row['pemeriksaan'] = $this->db('pemeriksaan_ranap')
        ->where('no_rawat', $no_rawat)
        ->limit(1)
        ->desc('tgl_perawatan')
        ->oneArray();
      $rtl = $pemeriksaan['rtl'] ?? null;
    }

    $__patientResp = $this->getPatient($no_ktp_pasien);
    $__patientJson = json_decode($__patientResp);
    $ihs_patient = '';
    if (is_object($__patientJson) && isset($__patientJson->entry) && is_array($__patientJson->entry) && isset($__patientJson->entry[0]) && isset($__patientJson->entry[0]->resource) && isset($__patientJson->entry[0]->resource->id)) {
      $ihs_patient = $__patientJson->entry[0]->resource->id;
    }

    $encounter_id = $mlite_satu_sehat_response['id_encounter'] ?? null;

    if ($ihs_patient === '' || !$rtl || !$encounter_id) {
      $error = [
        'error' => 'Data tidak lengkap untuk CarePlan',
        'missing' => [
          'patient_id' => $ihs_patient === '' ? 'missing' : 'ok',
          'rtl' => !$rtl ? 'missing' : 'ok',
          'id_encounter' => !$encounter_id ? 'missing' : 'ok'
        ]
      ];
      $response = json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      if ($render) {
        echo $this->draw('careplan.html', ['pesan' => 'Gagal mengirim careplan platform Satu Sehat!!', 'response' => $response]);
      } else {
        echo $response;
      }
      exit();
    }

    $careplan = '{
        "resourceType" : "CarePlan", 
        "identifier" : {
            "system" : "http://sys-ids.kemkes.go.id/careplan/' . $this->organizationid . '", 
            "value" : "' . $no_rawat . '"
        }, 
        "title" : "Instruksi Medik dan Keperawatan Pasien", 
        "status" : "active", 
        "category" : [
            {
                "coding" : [
                    {
                        "system" : "http://snomed.info/sct", 
                        "code" : "736271009", 
                        "display" : "Outpatient care plan"
                    }
                ]
            }
        ], 
        "intent" : "plan", 
        "description" : "' . $rtl . '", 
        "subject" : {
            "reference" : "Patient/' . $ihs_patient . '", 
            "display" : "' . $nama_pasien . '"
        }, 
        "encounter" : {
            "reference": "Encounter/' . $encounter_id . '",
            "display": "' . $kunjungan . ' ' . $nama_pasien . ' dari tanggal ' . $tgl_registrasi . '"
        }, 
        "created" : "' . $pemeriksaan['tgl_perawatan'] . 'T' . $pemeriksaan['jam_rawat'] . $zonawaktu . '", 
        "author" : {
            "reference" : "Practitioner/' . $id_dokter['practitioner_id'] . '", 
            "display" : "' . $nama_dokter . '"
        }
      }';

    // echo '<pre>'.$careplan.'</pre>';

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/CarePlan',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . json_decode($this->getToken())->access_token),
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $careplan,
    ));

    $response = curl_exec($curl);

    $decoded = json_decode($response);
    $id_careplan = (is_object($decoded) && isset($decoded->id)) ? $decoded->id : null;
    $pesan = 'Gagal mengirim careplan platform Satu Sehat!!';
    if ($id_careplan) {
      $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();
      if ($mlite_satu_sehat_response) {
        $this->db('mlite_satu_sehat_response')
          ->where('no_rawat', $no_rawat)
          ->save([
            'no_rawat' => $no_rawat,
            'id_careplan' => $id_careplan
          ]);
      } else {
        $this->db('mlite_satu_sehat_response')
          ->save([
            'no_rawat' => $no_rawat,
            'id_careplan' => $id_careplan
          ]);
      }
      $pesan = 'Sukses mengirim careplan platform Satu Sehat!!';
    }

    curl_close($curl);

    if ($render) {
      echo $this->draw('careplan.html', ['pesan' => $pesan, 'response' => $response]);
    } else {
      echo $response;
    }
    exit();
  }

  public function postSaveRad()
  {
    if (isset($_POST['simpan'])) {
      $query = $this->db('mlite_satu_sehat_mapping_rad')->save(
        [
          'kd_jenis_prw' => $_POST['kd_jenis_prw'],
          'code' => $_POST['code'],
          'system' => $_POST['code_system'],
          'display' => $_POST['display'],
          'sampel_code' => $_POST['sample_code'],
          'sampel_system' => $_POST['sample_system'],
          'sampel_display' => $_POST['sample_display']
        ]
      );

      if ($query) {
        $this->notify('success', 'Mapping radiologi telah disimpan');
      } else {
        $this->notify('danger', 'Mapping radiologi gagal disimpan');
      }
    }

    if (isset($_POST['hapus'])) {
      $query = $this->db('mlite_satu_sehat_mapping_rad')
        ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
        ->delete();
      if ($query) {
        $this->notify('success', 'Mapping radiologi telah dihapus');
      } else {
        $this->notify('danger', 'Mapping radiologi gagal dihapus');
      }
    }

    redirect(url([ADMIN, 'satu_sehat', 'mappingrad']));
  }

  public function postSaveLab()
  {
    if (isset($_POST['simpan'])) {
      $parts = explode(":", $_POST['id_template']);
      $id_template = trim($parts[0]);
      $kd_jenis_prw = trim($parts[1]);
      $query = $this->db('mlite_satu_sehat_mapping_lab')->save(
        [
          'id_template' => $id_template,
          'kd_jenis_prw' => $kd_jenis_prw,
          'code' => $_POST['code'],
          'system' => $_POST['code_system'],
          'display' => $_POST['display'],
          'sampel_code' => $_POST['sample_code'],
          'sampel_system' => $_POST['sample_system'],
          'sampel_display' => $_POST['sample_display']
        ]
      );

      if ($query) {
        $this->notify('success', 'Mapping laboratorium telah disimpan');
      } else {
        $this->notify('danger', 'Mapping laboratorium gagal disimpan');
      }
    }

    if (isset($_POST['hapus'])) {
      $query = $this->db('mlite_satu_sehat_mapping_lab')
        ->where('id_template', $_POST['id_template'])
        ->delete();
      if ($query) {
        $this->notify('success', 'Mapping laboratorium telah dihapus');
      } else {
        $this->notify('danger', 'Mapping laboratorium gagal dihapus');
      }
    }

    redirect(url([ADMIN, 'satu_sehat', 'mappinglab']));
  }

  public function postSaveDepartemen()
  {
    if (isset($_POST['simpan'])) {

      $get_id_organisasi_satusehat = json_decode($this->getOrganization($_POST['dep_id']));

      if ($get_id_organisasi_satusehat->issue[0]->code == 'duplicate') {

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $this->fhirurl . '/Organization?partof=' . $this->organizationid,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
          CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $get_id_organisasi_satusehat = json_decode($response);
        $get_id_organisasi_satusehat = json_encode($get_id_organisasi_satusehat, true);
        // echo $get_id_organisasi_satusehat;

        foreach (json_decode($get_id_organisasi_satusehat)->entry as $item) {
          if ($item->resource->identifier[0]->value == $_POST['dep_id']) {
            $id_organisasi_satusehat = $item->resource->id;
            echo $id_organisasi_satusehat;
          }
        }
      }

      if ($id_organisasi_satusehat != '') {
        $query = $this->db('mlite_satu_sehat_departemen')->save(
          [
            'dep_id' => $_POST['dep_id'],
            'id_organisasi_satusehat' => $id_organisasi_satusehat
          ]
        );
        if ($query) {
          $this->notify('success', 'Mapping departemen telah disimpan');
        } else {
          $this->notify('danger', 'Mapping departemen gagal disimpan');
        }
      }
    }

    if (isset($_POST['update'])) {
      $mlite_satu_sehat_departemen = $this->db('mlite_satu_sehat_departemen')->where('id_organisasi_satusehat', $_POST['id_organisasi_satusehat'])->oneArray();
      $query = $this->db('mlite_satu_sehat_departemen')
        ->where('id_organisasi_satusehat', $mlite_satu_sehat_departemen['id_organisasi_satusehat'])
        ->save(
          [
            'dep_id' => $_POST['dep_id']
          ]
        );
      if ($query) {
        $this->notify('success', 'Mapping departemen telah disimpan');
      }
    }

    if (isset($_POST['hapus'])) {
      $query = $this->db('mlite_satu_sehat_departemen')
        ->where('id_organisasi_satusehat', $_POST['id_organisasi_satusehat'])
        ->delete();
      if ($query) {
        $this->notify('success', 'Mapping departemen telah dihapus');
      }
    }

    redirect(url([ADMIN, 'satu_sehat', 'departemen']));
  }

  public function getProcedure($no_rawat, $render = true)
  {

    $zonawaktu = '+07:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WITA') {
      $zonawaktu = '+08:00';
    }
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WIT') {
      $zonawaktu = '+09:00';
    }


    $no_rawat = revertNoRawat($no_rawat);
    $kd_poli = $this->core->getRegPeriksaInfo('kd_poli', $no_rawat);
    $nm_poli = $this->core->getPoliklinikInfo('nm_poli', $kd_poli);
    $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $no_ktp_dokter = $this->core->getPegawaiInfo('no_ktp', $kd_dokter);
    $nama_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
    $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
    $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
    $nama_pasien = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
    $status_lanjut = $this->core->getRegPeriksaInfo('status_lanjut', $no_rawat);
    $tgl_registrasi = $this->core->getRegPeriksaInfo('tgl_registrasi', $no_rawat);
    $jam_reg = $this->core->getRegPeriksaInfo('jam_reg', $no_rawat);
    $mlite_billing = $this->db('mlite_billing')->where('no_rawat', $no_rawat)->oneArray();
    $prosedur_pasien = $this->db('prosedur_pasien')
      ->join('icd9', 'icd9.kode=prosedur_pasien.kode')
      ->where('no_rawat', $no_rawat)
      ->where('prosedur_pasien.status', $status_lanjut)
      ->where('prioritas', '1')
      ->oneArray();

    $pemeriksaan_ralan = $this->db('pemeriksaan_ralan')->where('no_rawat', $no_rawat)->oneArray();

    if (!is_array($prosedur_pasien) || !isset($prosedur_pasien['kode']) || !isset($prosedur_pasien['deskripsi_panjang'])) {
      $resp = json_encode(['error' => 'Data tidak lengkap untuk Procedure', 'missing' => ['prosedur_pasien.kode' => 'missing', 'prosedur_pasien.deskripsi_panjang' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      if ($render) {
        echo $this->draw('procedure.html', ['pesan' => 'Gagal mengirim procedure platform Satu Sehat!!', 'response' => $resp]);
      } else {
        echo $resp;
      }
      exit();
    }

    $kode_icd9 = $prosedur_pasien['kode'];
    $deskripsi_icd9 = $prosedur_pasien['deskripsi_panjang'];

    $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();

    $__patientResp = $this->getPatient($no_ktp_pasien);
    $__patientJson = json_decode($__patientResp);
    $id_pasien = '';
    if (is_object($__patientJson) && isset($__patientJson->entry) && is_array($__patientJson->entry) && isset($__patientJson->entry[0]) && isset($__patientJson->entry[0]->resource) && isset($__patientJson->entry[0]->resource->id)) {
      $id_pasien = $__patientJson->entry[0]->resource->id;
    }
    if ($id_pasien === '') {
      $resp = json_encode(['error' => 'Data tidak lengkap untuk Procedure', 'missing' => ['patient_id' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      if ($render) {
        echo $this->draw('procedure.html', ['pesan' => 'Gagal mengirim procedure platform Satu Sehat!!', 'response' => $resp]);
      } else {
        echo $resp;
      }
      exit();
    }
    $id_encounter = $mlite_satu_sehat_response['id_encounter'];
    $tgl_pulang = isset_or($mlite_billing['tgl_billing'], $pemeriksaan_ralan['tgl_perawatan']);
    $jam_pulang = isset_or($mlite_billing['jam_billing'], $pemeriksaan_ralan['jam_rawat']);

    $kunjungan = 'Kunjungan';
    if ($status_lanjut == 'Ranap') {
      $kunjungan = 'Perawatan';
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Procedure',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => '{
        "resourceType": "Procedure", 
        "status": "completed",
        "category": {
            "coding": [
                {
                    "system": "http://snomed.info/sct", 
                    "code": "103693007", 
                    "display": "Diagnostic procedure"
                }
            ], 
            "text":"Diagnostic procedure"
        }, 
        "code": {
            "coding": [
                {
                    "system": "http://hl7.org/fhir/sid/icd-9-cm", 
                    "code": "' . $kode_icd9 . '", 
                    "display": "' . $deskripsi_icd9 . '"
                
                }
            ]
        }, 
        "subject": {
            "reference": "Patient/' . $id_pasien . '", 
            "display": "' . $nama_pasien . '"
        }, 
        "encounter": {
            "reference": "Encounter/' . $id_encounter . '", 
            "display": "Prosedur kepada ' . $nama_pasien . ' selama ' . $kunjungan . ' dari tanggal ' . $tgl_registrasi . 'T' . $jam_reg . '' . $zonawaktu . ' sampai ' . $tgl_pulang . 'T' . $jam_pulang . '' . $zonawaktu . '"
        }, 
        "performedPeriod": {
            "start": "' . $tgl_registrasi . 'T' . $jam_reg . '' . $zonawaktu . '",
            "end": "' . $tgl_pulang . 'T' . $jam_pulang . '' . $zonawaktu . '"
        }
      }',
    ));

    $response = curl_exec($curl);

    $id_procedure = isset(json_decode($response)->id) ? json_decode($response)->id : null;
    $pesan = 'Gagal mengirim procedure platform Satu Sehat!!';
    if ($id_procedure) {
      $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();
      if ($mlite_satu_sehat_response) {
        $this->db('mlite_satu_sehat_response')
          ->where('no_rawat', $no_rawat)
          ->save([
            'no_rawat' => $no_rawat,
            'id_procedure' => $id_procedure
          ]);
      } else {
        $this->db('mlite_satu_sehat_response')
          ->save([
            'no_rawat' => $no_rawat,
            'id_procedure' => $id_procedure
          ]);
      }
      $pesan = 'Sukses mengirim procedure platform Satu Sehat!!';
    }

    curl_close($curl);
    // echo $response;
    if ($render) {
      echo $this->draw('procedure.html', ['pesan' => $pesan, 'response' => $response]);
    } else {
      $data = json_decode($response);
      echo $data ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $response;
    }
    exit();
  }

  public function getDietGizi($no_rawat, $render = true)
  {

    $zonawaktu = '+07:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WITA') {
      $zonawaktu = '+08:00';
    }
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WIT') {
      $zonawaktu = '+09:00';
    }


    $no_rawat = revertNoRawat($no_rawat);
    $kd_poli = $this->core->getRegPeriksaInfo('kd_poli', $no_rawat);
    $nm_poli = $this->core->getPoliklinikInfo('nm_poli', $kd_poli);
    $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $no_ktp_dokter = $this->core->getPegawaiInfo('no_ktp', $kd_dokter);
    $nama_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
    $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
    $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
    $nama_pasien = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
    $status_lanjut = $this->core->getRegPeriksaInfo('status_lanjut', $no_rawat);
    $tgl_registrasi = $this->core->getRegPeriksaInfo('tgl_registrasi', $no_rawat);
    $jam_reg = $this->core->getRegPeriksaInfo('jam_reg', $no_rawat);
    $mlite_billing = $this->db('mlite_billing')->where('no_rawat', $no_rawat)->oneArray();
    $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);

    $id_dokter = $this->db('mlite_satu_sehat_mapping_praktisi')->select('practitioner_id')->where('kd_dokter', $kd_dokter)->oneArray();

    $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();

    $__patientResp = $this->getPatient($no_ktp_pasien);
    $__patientJson = json_decode($__patientResp);
    $id_pasien = '';
    if (is_object($__patientJson) && isset($__patientJson->entry) && is_array($__patientJson->entry) && isset($__patientJson->entry[0]) && isset($__patientJson->entry[0]->resource) && isset($__patientJson->entry[0]->resource->id)) {
      $id_pasien = $__patientJson->entry[0]->resource->id;
    }
    if ($id_pasien === '') {
      $resp = json_encode(['error' => 'Data tidak lengkap untuk Diet Gizi', 'missing' => ['patient_id' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      if ($render) {
        echo $this->draw('dietgizi.html', ['pesan' => 'Gagal mengirim diet gizi platform Satu Sehat!!', 'response' => $resp]);
      } else {
        echo $resp;
      }
      exit();
    }
    $id_encounter = $mlite_satu_sehat_response['id_encounter'];

    $date = date('Y-m-d');
    $time = date('H:i:s');

    $nama_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
    $tgl_registrasi = $this->core->getRegPeriksaInfo('tgl_registrasi', $no_rawat);
    $adime_gizi = $this->db('catatan_adime_gizi')->where('no_rawat', $no_rawat)->oneArray();
    $instruksi = isset_or($adime_gizi['instruksi'], '');

    $curl = curl_init();

    $data = '{
      "resourceType" : "Composition",
      "identifier" : {
          "system" : "http://sys-ids.kemkes.go.id/composition/' . $this->organizationid . '",
          "value" : "' . $no_rawat . '"
      },
      "status" : "final",
      "type" : {
          "coding" : [
              {
                  "system" : "http://loinc.org",
                  "code" : "18842-5",
                  "display" : "Discharge summary"
              }
          ]
      },
      "category" : [
          {
              "coding" : [
                  {
                      "system" : "http://loinc.org",
                      "code" : "LP173421-1",
                      "display" : "Report"
                  }
              ]
          }
      ],
      "subject" : {
          "reference" : "Patient/' . $id_pasien . '",
          "display" : "' . $nama_pasien . '"
      },
      "encounter" : {
          "reference" : "Encounter/' . $id_encounter . '", 
          "display" : "Kunjungan ' . $nama_pasien . ' pada tanggal ' . $tgl_registrasi . ' dengan nomor kunjungan ' . $no_rawat . '"
      },
      "date" : "' . $date . 'T' . $time . '' . $zonawaktu . '", 
      "author" : [
          {
              "reference" : "Practitioner/' . $id_dokter['practitioner_id'] . '",
              "display" : "' . $nama_dokter . '"
          }
      ],
      "title" : "Modul Gizi",
      "custodian" : {
          "reference" : "Organization/' . $this->organizationid . '" 
      },
      "section" : [
          {
              "code" : {
                  "coding" : [
                      {
                          "system" : "http://loinc.org",
                          "code" : "42344-2",
                          "display" : "Discharge diet (narrative)"
                      }
                  ]
              },
              "text" : {
                  "status" : "additional",
                  "div" : "' . $instruksi . '"
              }
          }
      ]
    }';

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/Composition',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $data
    ));

    $response = curl_exec($curl);

    $id_composition = isset_or(json_decode($response)->id, '');
    $pesan = 'Gagal mengirim composition platform Satu Sehat!!';
    if ($id_composition) {
      $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();
      if ($mlite_satu_sehat_response) {
        $this->db('mlite_satu_sehat_response')
          ->where('no_rawat', $no_rawat)
          ->save([
            'no_rawat' => $no_rawat,
            'id_composition' => $id_composition
          ]);
      } else {
        $this->db('mlite_satu_sehat_response')
          ->save([
            'no_rawat' => $no_rawat,
            'id_composition' => $id_composition
          ]);
      }
      $pesan = 'Sukses mengirim id_composition platform Satu Sehat!!';
    }

    curl_close($curl);
    // echo $response;
    // echo '<pre>' . $data . '</pre>';
    if ($render) {
      echo $this->draw('dietgizi.html', ['pesan' => $pesan, 'response' => $response]);
    } else {
      echo $response;
    }
    exit();
  }

  public function getQuestionnaire($no_rawat, $render = true)
  {

    $zonawaktu = '+07:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WITA') {
      $zonawaktu = '+08:00';
    }
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WIT') {
      $zonawaktu = '+09:00';
    }


    $no_rawat = revertNoRawat($no_rawat);
    $kd_poli = $this->core->getRegPeriksaInfo('kd_poli', $no_rawat);
    $nm_poli = $this->core->getPoliklinikInfo('nm_poli', $kd_poli);
    $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $no_ktp_dokter = $this->core->getPegawaiInfo('no_ktp', $kd_dokter);
    $nama_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
    $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
    $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
    $nama_pasien = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
    $status_lanjut = $this->core->getRegPeriksaInfo('status_lanjut', $no_rawat);
    $tgl_registrasi = $this->core->getRegPeriksaInfo('tgl_registrasi', $no_rawat);
    $jam_reg = $this->core->getRegPeriksaInfo('jam_reg', $no_rawat);
    $mlite_billing = $this->db('mlite_billing')->where('no_rawat', $no_rawat)->oneArray();
    $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);

    $id_dokter = $this->db('mlite_satu_sehat_mapping_praktisi')->select('practitioner_id')->where('kd_dokter', $kd_dokter)->oneArray();

    $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();

    $__patientResp = $this->getPatient($no_ktp_pasien);
    $__patientJson = json_decode($__patientResp);
    $id_pasien = '';
    if (is_object($__patientJson) && isset($__patientJson->entry) && is_array($__patientJson->entry) && isset($__patientJson->entry[0]) && isset($__patientJson->entry[0]->resource) && isset($__patientJson->entry[0]->resource->id)) {
      $id_pasien = $__patientJson->entry[0]->resource->id;
    }
    if ($id_pasien === '') {
      $resp = json_encode(['error' => 'Data tidak lengkap untuk Diet Gizi', 'missing' => ['patient_id' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      if ($render) {
        echo $this->draw('dietgizi.html', ['pesan' => 'Gagal mengirim diet gizi platform Satu Sehat!!', 'response' => $resp]);
      } else {
        echo $resp;
      }
      exit();
    }
    $id_encounter = $mlite_satu_sehat_response['id_encounter'];

    $date = date('Y-m-d');
    $time = date('H:i:s');

    $nama_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
    $tgl_registrasi = $this->core->getRegPeriksaInfo('tgl_registrasi', $no_rawat);
    $adime_gizi = $this->db('catatan_adime_gizi')->where('no_rawat', $no_rawat)->oneArray();
    $instruksi = isset_or($adime_gizi['instruksi'], '');

    $catatan_perawatan = $this->db('catatan_perawatan')->where('no_rawat', $no_rawat)->where('kd_dokter', $kd_dokter)->where('catatan', 'KPS')->oneArray();

    $curl = curl_init();

    $data = '{
        "resourceType": "QuestionnaireResponse",
        "questionnaire": "https://fhir.kemkes.go.id/Questionnaire/Q0002",
        "status": "completed",
        "subject": {
            "reference" : "Patient/' . $id_pasien . '",
            "display" : "' . $nama_pasien . '"
        },
        "encounter": {
            "reference" : "Encounter/' . $id_encounter . '"
        },
        "authored": "' . $catatan_perawatan['tanggal'] . 'T' . $catatan_perawatan['jam'] . '' . $zonawaktu . '",
        "author": {
            "reference": "Practitioner/' . $id_dokter['practitioner_id'] . '"
        },
        "source": {
            "reference": "Patient/' . $id_pasien . '"
        },
        "item": [
            {
                "linkId": "1",
                "text": "Status Kesejahteraan",
                "answer": [
                    {
                        "valueString": "Keluarga Pra Sejahtera (KPS)"
                    }
                ]
            }
        ]
    }';

    // echo '<pre>' . $data . '</pre>';

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/QuestionnaireResponse',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $data
    ));

    $response = curl_exec($curl);

    $id_questionnaire = isset_or(json_decode($response)->id, '');
    $pesan = 'Gagal mengirim questionnaire response platform Satu Sehat!!';
    if ($id_questionnaire) {
      $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();
      if ($mlite_satu_sehat_response) {
        $this->db('mlite_satu_sehat_response')
          ->where('no_rawat', $no_rawat)
          ->save([
            'no_rawat' => $no_rawat,
            'id_questionnaire' => $id_questionnaire
          ]);
      } else {
        $this->db('mlite_satu_sehat_response')
          ->save([
            'no_rawat' => $no_rawat,
            'id_questionnaire' => $id_questionnaire
          ]);
      }
      $pesan = 'Sukses mengirim questionnaire response platform Satu Sehat!!';
    }

    curl_close($curl);
    // echo $response;
    // echo '<pre>' . $data . '</pre>';
    if ($render) {
      echo $this->draw('questionnaire.html', ['pesan' => $pesan, 'response' => $response]);
    } else {
      echo $response;
    }
    exit();
  }

  public function getMappingVaksin()
  {
    $this->_addHeaderFiles();
 
    // Ambil semua mapping vaksin + join databarang untuk nama obat lokal
    $mapping_vaksin = $this->db('satu_sehat_mapping_vaksin')
        ->join('databarang', 'databarang.kode_brng = satu_sehat_mapping_vaksin.kode_brng')
        ->toArray();
 
    // Ambil semua databarang untuk dropdown pilih obat
    $databarang = $this->db('databarang')
        ->asc('nama_brng')
        ->toArray();
 
    return $this->draw('mapping.vaksin.html', [
        'mapping_vaksin' => $mapping_vaksin,
        'databarang'     => $databarang,
    ]);
  }
 
  public function postSaveVaksin()
  {
    header('Content-Type: application/json');
 
    $kode_brng          = $_POST['kode_brng']          ?? '';
    $vaksin_code        = trim($_POST['vaksin_code']        ?? '');
    $vaksin_system      = trim($_POST['vaksin_system']      ?? 'http://sys-ids.kemkes.go.id/kfa');
    $vaksin_display     = trim($_POST['vaksin_display']     ?? '');
    $route_code         = trim($_POST['route_code']         ?? '');
    $route_system       = trim($_POST['route_system']       ?? 'http://www.whocc.no/atc');
    $route_display      = trim($_POST['route_display']      ?? '');
    $dose_quantity_code = trim($_POST['dose_quantity_code'] ?? '');
    $dose_quantity_system = trim($_POST['dose_quantity_system'] ?? 'http://unitsofmeasure.org');
    $dose_quantity_unit = trim($_POST['dose_quantity_unit'] ?? '');
 
    if (!$kode_brng) {
        echo json_encode(['success' => false, 'message' => 'Kode barang wajib diisi']);
        exit();
    }
 
    $existing = $this->db('satu_sehat_mapping_vaksin')
        ->where('kode_brng', $kode_brng)->oneArray();
 
    $data = [
        'kode_brng'            => $kode_brng,
        'vaksin_code'          => $vaksin_code,
        'vaksin_system'        => $vaksin_system,
        'vaksin_display'       => $vaksin_display,
        'route_code'           => $route_code,
        'route_system'         => $route_system,
        'route_display'        => $route_display,
        'dose_quantity_code'   => $dose_quantity_code,
        'dose_quantity_system' => $dose_quantity_system,
        'dose_quantity_unit'   => $dose_quantity_unit,
    ];
 
    if ($existing) {
        $this->db('satu_sehat_mapping_vaksin')
            ->where('kode_brng', $kode_brng)
            ->save($data);
        echo json_encode(['success' => true, 'message' => 'Mapping vaksin berhasil diupdate!']);
    } else {
        $this->db('satu_sehat_mapping_vaksin')->save($data);
        echo json_encode(['success' => true, 'message' => 'Mapping vaksin berhasil disimpan!']);
    }
    exit();
  }
 
  public function postDeleteVaksin()
  {
    header('Content-Type: application/json');
    $kode_brng = $_POST['kode_brng'] ?? '';
 
    if (!$kode_brng) {
        echo json_encode(['success' => false, 'message' => 'Kode barang tidak ditemukan']);
        exit();
    }
 
    $this->db('satu_sehat_mapping_vaksin')->where('kode_brng', $kode_brng)->delete();
    echo json_encode(['success' => true, 'message' => 'Mapping vaksin berhasil dihapus']);
    exit();
  }

  public function getVaksin($no_rawat, $render = true)
  {
    $zonawaktu = '+07:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WITA') $zonawaktu = '+08:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WIT')  $zonawaktu = '+09:00';
 
    $no_rawat = revertNoRawat($no_rawat);
 
    // ===== Data pasien & dokter =====
    $kd_poli       = $this->core->getRegPeriksaInfo('kd_poli', $no_rawat);
    $stts          = $this->core->getRegPeriksaInfo('stts', $no_rawat);
    $no_rkm_medis  = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
    $nm_pasien     = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
    $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
    $kd_dokter     = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $nm_dokter     = $this->core->getPegawaiInfo('nama', $kd_dokter);
    $id_dokter     = $this->db('mlite_satu_sehat_mapping_praktisi')
        ->select('practitioner_id')->where('kd_dokter', $kd_dokter)->oneArray();
 
    $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')
        ->where('no_rawat', $no_rawat)->oneArray();
 
    // ===== Patient ID Satu Sehat =====
    $__patientResp = $this->getPatient($no_ktp_pasien);
    $__patientJson = json_decode($__patientResp);
    $id_pasien     = '';
    if (is_object($__patientJson)
        && isset($__patientJson->entry)
        && is_array($__patientJson->entry)
        && isset($__patientJson->entry[0]->resource->id)
    ) {
        $id_pasien = $__patientJson->entry[0]->resource->id;
    }
 
    if ($id_pasien === '') {
        $resp = json_encode([
            'error'   => 'Data tidak lengkap untuk Vaksin',
            'missing' => ['patient_id' => 'missing'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($render) echo $this->draw('vaksin.html', ['pesan' => 'Gagal mengirim vaksin platform Satu Sehat!!', 'response' => $resp]);
        else echo $resp;
        exit();
    }
 
    $id_encounter = $mlite_satu_sehat_response['id_encounter'] ?? '';
 
    // ===== Lokasi =====
    // FIX: fallback ke lokasi pertama yang tersedia jika kd_poli tidak ada di mapping
    $is_ranap   = in_array($stts, ['Ranap', 'ranap', 'MRS']);
    $dep_lokasi = $this->db('satu_sehat_mapping_departemen')
        ->where('dep_id', $kd_poli)->oneArray();
 
    if ($is_ranap) {
        $lokasi_row = !empty($dep_lokasi['id_organisasi_satusehat'])
            ? $this->db('satu_sehat_mapping_lokasi_ranap')
                ->where('id_organisasi_satusehat', $dep_lokasi['id_organisasi_satusehat'])->oneArray()
            : $this->db('satu_sehat_mapping_lokasi_ranap')->oneArray();
    } else {
        $lokasi_row = !empty($dep_lokasi['id_organisasi_satusehat'])
            ? $this->db('satu_sehat_mapping_lokasi_ralan')
                ->where('id_organisasi_satusehat', $dep_lokasi['id_organisasi_satusehat'])->oneArray()
            : $this->db('satu_sehat_mapping_lokasi_ralan')->oneArray();
    }
 
    $id_lokasi   = $lokasi_row['id_lokasi_satusehat'] ?? '';
    $nama_lokasi = $lokasi_row['lokasi']              ?? '';
 
    // ===== Data resep vaksin =====
    $medications = $this->db('resep_obat')
        ->join('resep_dokter', 'resep_dokter.no_resep = resep_obat.no_resep')
        ->join('satu_sehat_mapping_vaksin', 'satu_sehat_mapping_vaksin.kode_brng = resep_dokter.kode_brng')
        ->where('resep_obat.no_rawat', $no_rawat)
        ->toArray();
 
    if (empty($medications)) {
        $resp = json_encode(['error' => 'Tidak ada data vaksin untuk no_rawat ini'], JSON_PRETTY_PRINT);
        if ($render) echo $this->draw('vaksin.html', ['pesan' => 'Data vaksin tidak ditemukan!', 'response' => $resp]);
        else echo $resp;
        exit();
    }
 
    $token = json_decode($this->getToken())->access_token;
 
    foreach ($medications as $obat) {
 
        $gudangbarang = $this->db('gudangbarang')
            ->where('kode_brng', $obat['kode_brng'])
            ->where('kd_bangsal', $this->core->getSettings('satu_sehat', 'farmasi'))
            ->oneArray();
 
        $databarang = $this->db('databarang')
            ->where('kode_brng', $obat['kode_brng'])
            ->oneArray();
 
        // Parsing aturan pakai → doseValue
        if (preg_match_all('/\d+/', $obat['aturan_pakai'] ?? '', $m) && count($m[0]) >= 2) {
          $doseValue = max(1, (int)$m[0][1]);
        } else {
            $doseValue = 1;
        }
 
        $lot_number = !empty($gudangbarang['no_batch'])
            ? $gudangbarang['no_batch']
            : ($gudangbarang['no_faktur'] ?? 'N/A');
 
        $occurrence_date = $obat['tgl_perawatan'] ?? date('Y-m-d');
        $expire_raw      = $databarang['expire'] ?? '';
        if (
            empty($expire_raw)
            || $expire_raw === '0000-00-00'
            || strtotime($expire_raw) === false
            || strtotime($expire_raw) <= strtotime($occurrence_date)
        ) {
            $expiration_date = date('Y-m-d', strtotime($occurrence_date . ' +2 years'));
        } else {
            $expiration_date = $expire_raw;
        }
 
        $data = '{
            "resourceType": "Immunization",
            "status": "completed",
            "vaccineCode": {
                "coding": [
                    {
                        "system": "' . ($obat['vaksin_system'] ?? 'http://sys-ids.kemkes.go.id/kfa') . '",
                        "code": "' . ($obat['vaksin_code'] ?? '') . '",
                        "display": "' . ($obat['vaksin_display'] ?? '') . '"
                    }
                ]
            },
            "patient": {
                "reference": "Patient/' . $id_pasien . '",
                "display": "' . $nm_pasien . '"
            },
            "encounter": {
                "reference": "Encounter/' . $id_encounter . '"
            },
            "occurrenceDateTime": "' . $occurrence_date . 'T' . ($obat['jam'] ?? '00:00:00') . $zonawaktu . '",
            "recorded": "' . $occurrence_date . 'T' . ($obat['jam'] ?? '00:00:00') . $zonawaktu . '",
            "primarySource": true,
            "location": {
                "reference": "Location/' . $id_lokasi . '",
                "display": "' . $nama_lokasi . '"
            },
            "lotNumber": "' . $lot_number . '",
            "expirationDate": "' . $expiration_date . '",
            "route": {
                "coding": [
                    {
                        "system": "' . ($obat['route_system'] ?? 'http://www.whocc.no/atc') . '",
                        "code": "' . ($obat['route_code'] ?? '') . '",
                        "display": "' . ($obat['route_display'] ?? '') . '"
                    }
                ]
            },
            "doseQuantity": {
                "value": ' . (int)($obat['jml'] ?? 1) . ',
                "unit": "' . ($obat['dose_quantity_unit'] ?? '') . '",
                "system": "' . ($obat['dose_quantity_system'] ?? 'http://unitsofmeasure.org') . '",
                "code": "' . ($obat['dose_quantity_code'] ?? '') . '"
            },
            "performer": [
                {
                    "function": {
                        "coding": [
                            {
                                "system": "http://terminology.hl7.org/CodeSystem/v2-0443",
                                "code": "AP",
                                "display": "Administering Provider"
                            }
                        ]
                    },
                    "actor": {
                        "reference": "Practitioner/' . ($id_dokter['practitioner_id'] ?? '') . '",
                        "display": "' . $nm_dokter . '"
                    }
                }
            ],
            "reasonCode": [
                {
                    "coding": [
                        {
                            "system": "http://terminology.kemkes.go.id/CodeSystem/immunization-reason",
                            "code": "IM-Program",
                            "display": "Imunisasi Program"
                        }
                    ]
                }
            ],
            "protocolApplied": [
                {
                    "doseNumberPositiveInt": ' . (int)$doseValue . '
                }
            ]
        }';
 
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->fhirurl . '/Immunization',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $data,
        ]);
 
        $response        = curl_exec($curl);
        $id_immunization = isset_or(json_decode($response)->id, '');
        $pesan           = 'Gagal mengirim vaksin/imunisasi platform Satu Sehat!!';
 
        if ($id_immunization) {
            $this->db('mlite_satu_sehat_response')
                ->where('no_rawat', $no_rawat)
                ->save(['id_immunization' => $id_immunization]);
            $pesan = 'Sukses mengirim vaksin/imunisasi platform Satu Sehat!!';
        }
 
        curl_close($curl);
    }
 
    if ($render) {
        echo $this->draw('vaksin.html', [
            'pesan'    => $pesan    ?? '',
            'response' => $response ?? '',
        ]);
    } else {
        echo $response ?? '';
    }
    exit();
}


  public function getClinicalImpression($no_rawat, $render = true)
  {

    $zonawaktu = '+07:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WITA') {
      $zonawaktu = '+08:00';
    }
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WIT') {
      $zonawaktu = '+09:00';
    }

    $no_rawat = revertNoRawat($no_rawat);

    $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();
    $pemeriksaan_ralan = $this->db('pemeriksaan_ralan')->where('no_rawat', $no_rawat)->oneArray();

    $keluhan = isset_or($pemeriksaan_ralan['keluhan'], '');
    $pemeriksaan = isset_or($pemeriksaan_ralan['pemeriksaan'], '');
    $penilaian = isset_or($pemeriksaan_ralan['penilaian'], '');
    $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
    $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
    $nama_pasien = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
    $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $tgl_perawatan = isset_or($pemeriksaan_ralan['tgl_perawatan'], date('Y-m-d'));
    $jam_rawat = isset_or($pemeriksaan_ralan['jam_rawat'], date('H:i:s'));
    $id_dokter = $this->db('mlite_satu_sehat_mapping_praktisi')->select('practitioner_id')->where('kd_dokter', $kd_dokter)->oneArray();
    $nama_dokter = $this->db('dokter')->where('kd_dokter', $kd_dokter)->oneArray();
    $id_condition = isset_or($mlite_satu_sehat_response['id_condition'], '');
    $diagnosa_pasien = $this->db('diagnosa_pasien')
      ->join('penyakit', 'penyakit.kd_penyakit=diagnosa_pasien.kd_penyakit')
      ->where('no_rawat', $no_rawat)
      ->where('prioritas', '1')
      ->oneArray();

    $kd_penyakit = isset_or($diagnosa_pasien['kd_penyakit'], '');
    $nm_penyakit = isset_or($diagnosa_pasien['nm_penyakit'], '');


    $__patientResp = $this->getPatient($no_ktp_pasien);
    $__patientJson = json_decode($__patientResp);
    $id_pasien = '';
    if (is_object($__patientJson) && isset($__patientJson->entry) && is_array($__patientJson->entry) && isset($__patientJson->entry[0]) && isset($__patientJson->entry[0]->resource) && isset($__patientJson->entry[0]->resource->id)) {
      $id_pasien = $__patientJson->entry[0]->resource->id;
    }
    if ($id_pasien === '') {
      $resp = json_encode(['error' => 'Data tidak lengkap untuk Clinical Impression', 'missing' => ['patient_id' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      if ($render) {
        echo $this->draw('clinical.impression.html', ['pesan' => 'Gagal mengirim clinical impression platform Satu Sehat!!', 'response' => $resp]);
      } else {
        echo $resp;
      }
      exit();
    }

    $id_encounter = $mlite_satu_sehat_response['id_encounter'];

    $data = '{
      "resourceType": "ClinicalImpression",
      "status": "completed",
      "description": "Evaluasi klinis untuk pasien dengan ' . $keluhan . ', ' . $pemeriksaan . '.",
      "subject": {
        "reference": "Patient/' . $id_pasien . '"
      },
      "encounter": {
        "reference": "Encounter/' . $id_encounter . '"
      },
      "effectiveDateTime": "' . $tgl_perawatan . 'T' . $jam_rawat . '' . $zonawaktu . '",
      "date": "' . $tgl_perawatan . 'T' . $jam_rawat . '' . $zonawaktu . '",
      "assessor": {
        "reference": "Practitioner/' . $id_dokter['practitioner_id'] . '"
      },
      "summary": "' . $penilaian . '", 
      "finding": [
        {
          "itemCodeableConcept": {
            "coding": [
              {
                "system": "http://hl7.org/fhir/sid/icd-10",
                "code": "' . $kd_penyakit . '",
                "display": "' . $nm_penyakit . '"
              }
            ]
          },
          "itemReference": {
              "reference": "Condition/' . $id_condition . '" 
          }
        }
      ],
      "prognosisCodeableConcept": [
        {
          "coding": [
            {
              "system": "http://terminology.kemkes.go.id/CodeSystem/clinical-term",
              "code": "PR000001",
              "display": "Prognosis"
            }
          ]
        }
      ]
    }';

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/ClinicalImpression',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()),
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $data,
    ));

    $response = curl_exec($curl);

    $id_clinical_impression = isset(json_decode($response)->id) ? json_decode($response)->id : null;
    $pesan = 'Gagal mengirim clinical impression platform Satu Sehat!!';
    if ($id_clinical_impression) {
      $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();
      if ($mlite_satu_sehat_response) {
        $this->db('mlite_satu_sehat_response')
          ->where('no_rawat', $no_rawat)
          ->save([
            'no_rawat' => $no_rawat,
            'id_clinical_impression' => $id_clinical_impression
          ]);
      } else {
        $this->db('mlite_satu_sehat_response')
          ->save([
            'no_rawat' => $no_rawat,
            'id_clinical_impression' => $id_clinical_impression
          ]);
      }
      $pesan = 'Sukses mengirim clinical impression platform Satu Sehat!!';
    }

    curl_close($curl);
    // echo '<pre>' . $data . '</pre>';

    if ($render) {
      echo $this->draw('clinical.impression.html', ['pesan' => $pesan, 'response' => $response]);
    } else {
      echo $response;
    }
    exit();
  }

  public function getMedication(string $no_rawat = '', string $tipe = 'request', $render = true)
  {
    $zonawaktu = match ($this->settings->get('satu_sehat.zonawaktu')) {
        'WITA' => '+08:00',
        'WIT'  => '+09:00',
        default => '+07:00',
    };

    $kode_brng = $no_rawat;
    $no_rawat  = revertNoRawat($no_rawat);

    if ($tipe === 'request') {

        $row['medications'] = $this->db('resep_obat')
            ->join('resep_dokter', 'resep_dokter.no_resep = resep_obat.no_resep')
            ->join('satu_sehat_mapping_obat', 'satu_sehat_mapping_obat.kode_brng = resep_dokter.kode_brng')
            ->where('no_rawat', $no_rawat)
            ->toArray();

        if (count($row['medications']) === 0) {
            echo json_encode(['error' => 'Data tidak lengkap untuk Medication Request', 'missing' => ['noresep' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }

        $no_rkm_medis  = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
        $nm_pasien     = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
        $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
        $kd_dokter     = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
        $nm_dokter     = $this->core->getPegawaiInfo('nama', $kd_dokter);
        $id_dokter     = $this->db('mlite_satu_sehat_mapping_praktisi')
            ->select('practitioner_id')->where('kd_dokter', $kd_dokter)->oneArray();

        $__patientResp = $this->getPatient($no_ktp_pasien);
        $__patientJson = json_decode($__patientResp);
        $id_pasien = '';
        if (is_object($__patientJson) && isset($__patientJson->entry) && is_array($__patientJson->entry) && isset($__patientJson->entry[0]->resource->id)) {
            $id_pasien = $__patientJson->entry[0]->resource->id;
        }
        if ($id_pasien === '') {
            echo json_encode(['error' => 'Data tidak lengkap untuk Medication', 'missing' => ['patient_id' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }

        $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();

        foreach ($row['medications'] as $i => $obat) {
            $system_cek = 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm';
            if (ctype_digit($obat['denominator_code'])) $system_cek = 'http://snomed.info/sct';
            if (preg_match_all('/\d+/', $obat['aturan_pakai'], $m) && count($m[0]) >= 2) {
                $frequency = (int) $m[0][0]; $doseValue = (int) $m[0][1];
            } else { $frequency = 1; $doseValue = 1; }
            $satu_sehat_mapping_obat = $this->db('satu_sehat_mapping_obat')->where('kode_brng', $obat['kode_brng'])->oneArray();

            $data = '{
                "resourceType": "MedicationRequest",
                "identifier": [
                    {"system": "http://sys-ids.kemkes.go.id/prescription/' . $this->organizationid . '", "use": "official", "value": "' . $obat['no_resep'] . '"},
                    {"system": "http://sys-ids.kemkes.go.id/prescription-item/' . $this->organizationid . '", "use": "official", "value": "' . $obat['kode_brng'] . '"}
                ],
                "status": "completed", "intent": "order",
                "category": [{"coding": [{"system": "http://terminology.hl7.org/CodeSystem/medicationrequest-category", "code": "outpatient", "display": "Outpatient"}]}],
                "medicationReference": {"reference": "Medication/' . $satu_sehat_mapping_obat['id_medication'] . '", "display": "' . $satu_sehat_mapping_obat['obat_display'] . '"},
                "subject": {"reference": "Patient/' . $id_pasien . '", "display": "' . $nm_pasien . '"},
                "encounter": {"reference": "Encounter/' . $mlite_satu_sehat_response['id_encounter'] . '"},
                "authoredOn": "' . $obat['tgl_peresepan'] . 'T' . $obat['jam_peresepan'] . '' . $zonawaktu . '",
                "requester": {"reference": "Practitioner/' . $id_dokter['practitioner_id'] . '", "display": "' . $nm_dokter . '"},
                "dosageInstruction": [{
                    "sequence": 1, "patientInstruction": "' . $obat['aturan_pakai'] . '",
                    "timing": {"repeat": {"frequency": ' . $doseValue . ', "period": 1, "periodUnit": "d"}},
                    "route": {"coding": [{"system": "' . $satu_sehat_mapping_obat['route_system'] . '", "code": "' . $satu_sehat_mapping_obat['route_code'] . '", "display": "' . $satu_sehat_mapping_obat['route_display'] . '"}]},
                    "doseAndRate": [{"doseQuantity": {"value": ' . $frequency . ', "unit": "' . $satu_sehat_mapping_obat['denominator_code'] . '", "system": "' . $system_cek . '", "code": "' . $satu_sehat_mapping_obat['denominator_code'] . '"}}]
                }],
                "dispenseRequest": {
                    "quantity": {"value": ' . $obat['jml'] . ', "unit": "' . $satu_sehat_mapping_obat['denominator_code'] . '", "system": "' . $system_cek . '", "code": "' . $satu_sehat_mapping_obat['denominator_code'] . '"},
                    "performer": {"reference": "Organization/' . $this->organizationid . '"}
                }
            }';

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->fhirurl . '/MedicationRequest',
                CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()],
                CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => $data,
            ]);
            $response = curl_exec($curl);
            $id_medication_request = isset_or(json_decode($response)->id, '');
            $pesan = 'Gagal mengirim medication request platform Satu Sehat!!';
            if ($id_medication_request) {
                $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->save(['id_medication_request' => $id_medication_request]);
                $pesan = 'Sukses mengirim medication request platform Satu Sehat!!';
            }
            curl_close($curl);
        }

    } else if ($tipe === 'dispense') {

        $row['medications'] = $this->db('resep_obat')
            ->join('resep_dokter', 'resep_dokter.no_resep = resep_obat.no_resep')
            ->join('satu_sehat_mapping_obat', 'satu_sehat_mapping_obat.kode_brng = resep_dokter.kode_brng')
            ->where('no_rawat', $no_rawat)
            ->toArray();

        if (count($row['medications']) === 0) {
            echo json_encode(['error' => 'Data tidak lengkap untuk Medication Dispense', 'missing' => ['noresep' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }

        $no_rkm_medis  = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
        $nm_pasien     = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
        $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
        $kd_dokter     = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
        $nm_dokter     = $this->core->getPegawaiInfo('nama', $kd_dokter);
        $id_dokter     = $this->db('mlite_satu_sehat_mapping_praktisi')->select('practitioner_id')->where('kd_dokter', $kd_dokter)->oneArray();

        $__patientResp = $this->getPatient($no_ktp_pasien);
        $__patientJson = json_decode($__patientResp);
        $id_pasien = '';
        if (is_object($__patientJson) && isset($__patientJson->entry) && is_array($__patientJson->entry) && isset($__patientJson->entry[0]->resource->id)) {
            $id_pasien = $__patientJson->entry[0]->resource->id;
        }
        if ($id_pasien === '') {
            echo json_encode(['error' => 'Data tidak lengkap untuk Medication', 'missing' => ['patient_id' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }

        $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();
        $dep_farmasi       = $this->db('satu_sehat_mapping_departemen')->where('dep_id', $this->core->getSettings('satu_sehat', 'farmasi'))->oneArray();
        $lokasi_farmasi    = $this->db('satu_sehat_mapping_lokasi_depo_farmasi')->where('id_organisasi_satusehat', $dep_farmasi['id_organisasi_satusehat'] ?? '')->oneArray();
        $id_lokasi_farmasi = $lokasi_farmasi['id_lokasi_satusehat'] ?? '';

        foreach ($row['medications'] as $i => $obat) {
            $system_cek = 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm';
            if (ctype_digit($obat['denominator_code'])) $system_cek = 'http://snomed.info/sct';
            if (preg_match_all('/\d+/', $obat['aturan_pakai'], $m) && count($m[0]) >= 2) {
                $frequency = (int) $m[0][0]; $doseValue = (int) $m[0][1];
            } else { $frequency = 1; $doseValue = 1; }
            $satu_sehat_mapping_obat = $this->db('satu_sehat_mapping_obat')->where('kode_brng', $obat['kode_brng'])->oneArray();

            $data = '{
                "resourceType": "MedicationDispense",
                "identifier": [
                    {"system": "http://sys-ids.kemkes.go.id/prescription/' . $this->organizationid . '", "use": "official", "value": "' . $obat['no_resep'] . '"},
                    {"system": "http://sys-ids.kemkes.go.id/prescription-item/' . $this->organizationid . '", "use": "official", "value": "' . $obat['kode_brng'] . '"}
                ],
                "status": "completed",
                "category": {"coding": [{"system": "http://terminology.hl7.org/fhir/CodeSystem/medicationdispense-category", "code": "outpatient", "display": "Outpatient"}]},
                "medicationReference": {"reference": "Medication/' . $satu_sehat_mapping_obat['id_medication'] . '", "display": "' . $satu_sehat_mapping_obat['obat_display'] . '"},
                "subject": {"reference": "Patient/' . $id_pasien . '", "display": "' . $nm_pasien . '"},
                "context": {"reference": "Encounter/' . $mlite_satu_sehat_response['id_encounter'] . '"},
                "performer": [{"actor": {"reference": "Practitioner/' . $id_dokter['practitioner_id'] . '", "display": "' . $nm_dokter . '"}}],
                "location": {"reference": "Location/' . $id_lokasi_farmasi . '"},
                "authorizingPrescription": [{"reference": "MedicationRequest/' . $mlite_satu_sehat_response['id_medication_request'] . '"}],
                "quantity": {"system": "' . $system_cek . '", "code": "' . $satu_sehat_mapping_obat['denominator_code'] . '", "value": ' . $obat['jml'] . '},
                "whenPrepared": "' . $obat['tgl_peresepan'] . 'T' . $obat['jam_peresepan'] . '' . $zonawaktu . '",
                "whenHandedOver": "' . $obat['tgl_perawatan'] . 'T' . $obat['jam'] . '' . $zonawaktu . '",
                "dosageInstruction": [{
                    "sequence": 1, "text": "' . $obat['aturan_pakai'] . '",
                    "timing": {"repeat": {"frequency": ' . $doseValue . ', "period": 1, "periodUnit": "d"}},
                    "route": {"coding": [{"system": "' . $satu_sehat_mapping_obat['route_system'] . '", "code": "' . $satu_sehat_mapping_obat['route_code'] . '", "display": "' . $satu_sehat_mapping_obat['route_display'] . '"}]},
                    "doseAndRate": [{"doseQuantity": {"value": ' . $frequency . ', "unit": "' . $satu_sehat_mapping_obat['denominator_code'] . '", "system": "' . $system_cek . '", "code": "' . $satu_sehat_mapping_obat['denominator_code'] . '"}}]
                }]
            }';

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->fhirurl . '/MedicationDispense',
                CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . json_decode($this->getToken())->access_token],
                CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => $data,
            ]);
            $response = curl_exec($curl);
            $id_medication_dispense = isset_or(json_decode($response)->id, '');
            $pesan = 'Gagal mengirim medication dispense platform Satu Sehat!!';
            if ($id_medication_dispense) {
                $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->save(['id_medication_dispense' => $id_medication_dispense]);
                $pesan = 'Sukses mengirim medication dispense platform Satu Sehat!!';
            }
            curl_close($curl);
        }

    } else if ($tipe === 'statement') {

        $row['medications'] = $this->db('resep_obat')
            ->join('resep_dokter', 'resep_dokter.no_resep = resep_obat.no_resep')
            ->join('satu_sehat_mapping_obat', 'satu_sehat_mapping_obat.kode_brng = resep_dokter.kode_brng')
            ->where('no_rawat', $no_rawat)
            ->toArray();

        if (count($row['medications']) === 0) {
            echo json_encode(['error' => 'Data tidak lengkap untuk Medication Statement', 'missing' => ['noresep' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }

        $no_rkm_medis  = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
        $nm_pasien     = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
        $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
        $kd_dokter     = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
        $id_dokter     = $this->db('mlite_satu_sehat_mapping_praktisi')->select('practitioner_id')->where('kd_dokter', $kd_dokter)->oneArray();

        $__patientResp = $this->getPatient($no_ktp_pasien);
        $__patientJson = json_decode($__patientResp);
        $id_pasien = '';
        if (is_object($__patientJson) && isset($__patientJson->entry) && is_array($__patientJson->entry) && isset($__patientJson->entry[0]->resource->id)) {
            $id_pasien = $__patientJson->entry[0]->resource->id;
        }
        if ($id_pasien === '') {
            echo json_encode(['error' => 'Data tidak lengkap untuk Medication', 'missing' => ['patient_id' => 'missing']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit();
        }

        $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();

        foreach ($row['medications'] as $i => $obat) {
            $system_cek = 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm';
            if (ctype_digit($obat['denominator_code'])) $system_cek = 'http://snomed.info/sct';
            if (preg_match_all('/\d+/', $obat['aturan_pakai'], $m) && count($m[0]) >= 2) {
                $frequency = (int) $m[0][0]; $doseValue = (int) $m[0][1];
            } else { $frequency = 1; $doseValue = 1; }
            $satu_sehat_mapping_obat = $this->db('satu_sehat_mapping_obat')->where('kode_brng', $obat['kode_brng'])->oneArray();

            $data = '{
                "resourceType": "MedicationStatement",
                "identifier": [{"system": "http://sys-ids.kemkes.go.id/medicationstatement/' . $this->organizationid . '", "use": "official", "value": "' . $obat['no_resep'] . '-' . $obat['kode_brng'] . '"}],
                "status": "completed",
                "category": {"coding": [{"system": "http://terminology.hl7.org/CodeSystem/medication-statement-category", "code": "outpatient", "display": "Outpatient"}]},
                "medicationReference": {"reference": "Medication/' . $satu_sehat_mapping_obat['id_medication'] . '", "display": "' . $satu_sehat_mapping_obat['obat_display'] . '"},
                "subject": {"reference": "Patient/' . $id_pasien . '", "display": "' . $nm_pasien . '"},
                "dosage": [{
                    "text": "' . $obat['aturan_pakai'] . '",
                    "timing": {"repeat": {"frequency": ' . $doseValue . ', "period": 1, "periodUnit": "d"}},
                    "route": {"coding": [{"system": "' . $satu_sehat_mapping_obat['route_system'] . '", "code": "' . $satu_sehat_mapping_obat['route_code'] . '", "display": "' . $satu_sehat_mapping_obat['route_display'] . '"}]},
                    "doseAndRate": [{"doseQuantity": {"value": ' . $frequency . ', "unit": "' . $satu_sehat_mapping_obat['denominator_code'] . '", "system": "' . $system_cek . '", "code": "' . $satu_sehat_mapping_obat['denominator_code'] . '"}}]
                }],
                "dateAsserted": "' . $obat['tgl_penyerahan'] . 'T' . $obat['jam_penyerahan'] . '' . $zonawaktu . '",
                "informationSource": {"reference": "Patient/' . $id_pasien . '", "display": "' . $nm_pasien . '"},
                "context": {"reference": "Encounter/' . $mlite_satu_sehat_response['id_encounter'] . '"},
                "note": [{"text": "Sudah dilakukan proses telaah obat oleh petugas dan obat sudah diserahkan ke pasien."}]
            }';

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->fhirurl . '/MedicationStatement',
                CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . json_decode($this->getToken())->access_token],
                CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => $data,
            ]);
            $response = curl_exec($curl);
            $id_medication_statement = isset_or(json_decode($response)->id, '');
            $pesan = 'Gagal mengirim medication statement platform Satu Sehat!!';
            if ($id_medication_statement) {
                $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->save(['id_medication_statement' => $id_medication_statement]);
                $pesan = 'Sukses mengirim medication statement platform Satu Sehat!!';
            }
            curl_close($curl);
        }

    } else if ($tipe == 'mapping') {

        $pesan    = 'Gagal mengirim mapping medication platform Satu Sehat!!';
        $response = '';

        try {
            $satu_sehat_mapping_obat = $this->db('satu_sehat_mapping_obat')
                ->select('id_medication')
                ->where('kode_brng', $kode_brng)
                ->oneArray();
        } catch (\Exception $e) {
            $pesan    = 'GAGAL: Kolom id_medication belum ada! Jalankan: ALTER TABLE satu_sehat_mapping_obat ADD COLUMN id_medication varchar(40) NULL DEFAULT NULL;';
            $response = json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
            if ($render) echo $this->draw('medication.html', ['pesan' => $pesan, 'response' => $response]);
            else echo $response;
            exit();
        }

        $mapping = $this->db('satu_sehat_mapping_obat')->where('kode_brng', $kode_brng)->oneArray();

        if (empty($mapping)) {
            $pesan    = 'GAGAL: Data obat tidak ditemukan di database lokal!';
            $response = json_encode(['error' => 'kode_brng tidak ditemukan'], JSON_PRETTY_PRINT);
            if ($render) echo $this->draw('medication.html', ['pesan' => $pesan, 'response' => $response]);
            else echo $response;
            exit();
        }

        if (empty($mapping['obat_code']) || empty($mapping['form_code'])) {
            $pesan    = 'GAGAL: Data mapping tidak lengkap (obat_code atau form_code kosong)!';
            $response = json_encode(['error' => 'obat_code atau form_code kosong', 'data' => $mapping], JSON_PRETTY_PRINT);
            if ($render) echo $this->draw('medication.html', ['pesan' => $pesan, 'response' => $response]);
            else echo $response;
            exit();
        }

        $existing_id = $mapping['id_medication'] ?? '';

        if (!empty($existing_id)) {
            $payload = '{
                "resourceType": "Medication",
                "id": "' . $existing_id . '",
                "meta": {"profile": ["https://fhir.kemkes.go.id/r4/StructureDefinition/Medication"]},
                "identifier": [{"system": "http://sys-ids.kemkes.go.id/medication/' . $this->organizationid . '", "use": "official", "value": "' . $mapping['kode_brng'] . '"}],
                "code": {"coding": [{"system": "http://sys-ids.kemkes.go.id/kfa", "code": "' . $mapping['obat_code'] . '", "display": "' . $mapping['obat_display'] . '"}]},
                "status": "active",
                "form": {"coding": [{"system": "' . $mapping['form_system'] . '", "code": "' . $mapping['form_code'] . '", "display": "' . $mapping['form_display'] . '"}]},
                "extension": [{"url": "https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType",
                    "valueCodeableConcept": {"coding": [{"system": "http://terminology.kemkes.go.id/CodeSystem/medication-type", "code": "NC", "display": "Non-compound"}]}
                }]
            }';

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $this->fhirurl . '/Medication/' . $existing_id,
                CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT        => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()],
                CURLOPT_CUSTOMREQUEST  => 'PUT',
                CURLOPT_POSTFIELDS     => $payload,
            ]);
            $response = curl_exec($curl);
            $decoded  = json_decode($response);
            curl_close($curl);

            $updated_id = $decoded->id ?? null;
            if ($updated_id) {
                $pesan = 'Sukses UPDATE mapping medication di Satu Sehat!! (ID: ' . $updated_id . ')';
            } else {
                $pesan = 'Gagal UPDATE mapping medication di Satu Sehat!!';
            }

        } else {
            $payload = '{
                "resourceType": "Medication",
                "meta": {"profile": ["https://fhir.kemkes.go.id/r4/StructureDefinition/Medication"]},
                "identifier": [{"system": "http://sys-ids.kemkes.go.id/medication/' . $this->organizationid . '", "use": "official", "value": "' . $mapping['kode_brng'] . '"}],
                "code": {"coding": [{"system": "http://sys-ids.kemkes.go.id/kfa", "code": "' . $mapping['obat_code'] . '", "display": "' . $mapping['obat_display'] . '"}]},
                "status": "active",
                "form": {"coding": [{"system": "' . $mapping['form_system'] . '", "code": "' . $mapping['form_code'] . '", "display": "' . $mapping['form_display'] . '"}]},
                "extension": [{"url": "https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType",
                    "valueCodeableConcept": {"coding": [{"system": "http://terminology.kemkes.go.id/CodeSystem/medication-type", "code": "NC", "display": "Non-compound"}]}
                }]
            }';

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $this->fhirurl . '/Medication',
                CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT        => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()],
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => $payload,
            ]);
            $response = curl_exec($curl);
            $decoded  = json_decode($response);
            curl_close($curl);

            $id_medication = $decoded->id ?? null;

            // Cek duplicate
            $is_duplicate = false;
            if (isset($decoded->issue)) {
                foreach ($decoded->issue as $issue) {
                    if (isset($issue->code) && $issue->code === 'duplicate') {
                        $is_duplicate = true;
                        break;
                    }
                }
            }

            if ($id_medication) {
                $this->db('satu_sehat_mapping_obat')->where('kode_brng', $kode_brng)->save(['id_medication' => $id_medication]);
                $pesan = 'Sukses mengirim mapping medication platform Satu Sehat!! (ID: ' . $id_medication . ')';
            } elseif ($is_duplicate) {
                $pesan = 'DUPLICATE: Medication sudah ada di Satu Sehat sebelumnya. Silakan input ID Medication secara manual!';
            }
        }
    }

    if ($render) {
        echo $this->draw('medication.html', [
            'pesan'    => isset_or($pesan, ''),
            'response' => isset_or($response, '')
        ]);
    } else {
        echo isset($response) ? $response : '';
    }
    exit();
  }

  public function getAllergy($no_rawat, $render = true)
  {

    $zonawaktu = '+07:00';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WITA') {
      $zonawaktu = '+08:00';
    }
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WIT') {
      $zonawaktu = '+09:00';
    }


    $no_rawat = revertNoRawat($no_rawat);
    $kd_poli = $this->core->getRegPeriksaInfo('kd_poli', $no_rawat);
    $nm_poli = $this->core->getPoliklinikInfo('nm_poli', $kd_poli);
    $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', $no_rawat);
    $no_ktp_dokter = $this->core->getPegawaiInfo('no_ktp', $kd_dokter);
    $nama_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
    $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat);
    $no_ktp_pasien = $this->core->getPasienInfo('no_ktp', $no_rkm_medis);
    $nama_pasien = $this->core->getPasienInfo('nm_pasien', $no_rkm_medis);
    $status_lanjut = $this->core->getRegPeriksaInfo('status_lanjut', $no_rawat);
    $tgl_registrasi = $this->core->getRegPeriksaInfo('tgl_registrasi', $no_rawat);
    $jam_reg = $this->core->getRegPeriksaInfo('jam_reg', $no_rawat);
    $mlite_billing = $this->db('mlite_billing')->where('no_rawat', $no_rawat)->oneArray();
    $pemeriksaan = $this->db('pemeriksaan_ralan')->where('no_rawat', $no_rawat)->oneArray();

    $rtl = $pemeriksaan['rtl'] ?? null;

    $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();

    $id_dokter = $this->db('mlite_satu_sehat_mapping_praktisi')
      ->select('practitioner_id')
      ->where('kd_dokter', $kd_dokter)
      ->oneArray();

    $kunjungan = 'Kunjungan';
    if ($status_lanjut == 'Ranap') {
      $kunjungan = 'Perawatan';
      $row['pemeriksaan'] = $this->db('pemeriksaan_ranap')
        ->where('no_rawat', $no_rawat)
        ->limit(1)
        ->desc('tgl_perawatan')
        ->oneArray();
      $rtl = $pemeriksaan['rtl'] ?? null;
    }

    $__patientResp = $this->getPatient($no_ktp_pasien);
    $__patientJson = json_decode($__patientResp);
    $ihs_patient = '';
    if (is_object($__patientJson) && isset($__patientJson->entry) && is_array($__patientJson->entry) && isset($__patientJson->entry[0]) && isset($__patientJson->entry[0]->resource) && isset($__patientJson->entry[0]->resource->id)) {
      $ihs_patient = $__patientJson->entry[0]->resource->id;
    }

    $encounter_id = $mlite_satu_sehat_response['id_encounter'] ?? null;

    if ($ihs_patient === '' || !$encounter_id) {
      $error = [
        'error' => 'Data tidak lengkap untuk Allergy',
        'missing' => [
          'patient_id' => $ihs_patient === '' ? 'missing' : 'ok',
          'rtl' => !$rtl ? 'missing' : 'ok',
          'id_encounter' => !$encounter_id ? 'missing' : 'ok'
        ]
      ];
      $response = json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      if ($render) {
        echo $this->draw('allergy.html', ['pesan' => 'Gagal mengirim allergy platform Satu Sehat!!', 'response' => $response]);
      } else {
        echo $response;
      }
      exit();
    }

    $allergy_list = [
      [
        'deskripsi' => 'Allergy to food',
        'snomed_ct' => '414285001',
        'icd_10' => 'T78.1',
        'category' => 'food'
      ],
      [
        'deskripsi' => 'Allergy to drug',
        'snomed_ct' => '416098002',
        'icd_10' => 'T88.7',
        'category' => 'drug'
      ],
      [
        'deskripsi' => 'Allergy to nutraceutical',
        'snomed_ct' => '300910009',
        'icd_10' => 'J30.1',
        'category' => 'environment'
      ],
      [
        'deskripsi' => 'Allergy to dust',
        'snomed_ct' => '232350006',
        'icd_10' => 'J30.8',
        'category' => 'environment'
      ],
      [
        'deskripsi' => 'Allergic disposition',
        'snomed_ct' => '609328004',
        'icd_10' => 'T78.4',
        'category' => 'environment'
      ]
    ];

    $allergy_icd10 = array_column($allergy_list, 'icd_10');

    $row['allergy'] = [];

    $allergy = $this->db('diagnosa_pasien')
      ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
      ->where('no_rawat', $no_rawat)
      ->where('diagnosa_pasien.status', $status_lanjut)
      ->in('diagnosa_pasien.kd_penyakit', $allergy_icd10)
      ->oneArray();

    $allergy_map = array_column($allergy_list, null, 'icd_10');

    if (!empty($allergy) && isset($allergy_map[$allergy['kd_penyakit']])) {
      $row['allergy'] = $allergy_map[$allergy['kd_penyakit']];
    }

    $allergy = '{
        "resourceType" : "AllergyIntolerance", 
        "identifier" : {
            "system" : "http://sys-ids.kemkes.go.id/allergy/' . $this->organizationid . '", 
            "use" : "official",
            "value" : "' . $no_rawat . '"
        }, 
        "clinicalStatus": {
            "coding": [
                {
                    "system": "http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical",
                    "code": "active",
                    "display": "Active"
                }
            ]
        },
        "verificationStatus": {
            "coding": [
                {
                    "system": "http://terminology.hl7.org/CodeSystem/allergyintolerance-verification",
                    "code": "confirmed",
                    "display": "Confirmed"
                }
            ]
        },
        "category": [
            "food"
        ],
        "code": {
            "coding": [
                {
                    "system": "http://snomed.info/sct",
                    "code": "' . $row['allergy']['snomed_ct'] . '",
                    "display": "' . $row['allergy']['deskripsi'] . '"
                }
            ],
            "text": "' . $row['allergy']['deskripsi'] . '"
        },
        "patient": {
            "reference": "Patient/' . $ihs_patient . '",
            "display": "' . $nama_pasien . '"
        },
        "encounter": {
            "reference": "Encounter/' . $encounter_id . '",
            "display": "' . $kunjungan . ' ' . $nama_pasien . ' dari tanggal ' . $tgl_registrasi . '"
        },
        "recordedDate": "' . $pemeriksaan['tgl_perawatan'] . 'T' . $pemeriksaan['jam_rawat'] . $zonawaktu . '",
        "recorder": {
            "reference": "Practitioner/' . $id_dokter['practitioner_id'] . '",
            "display": "' . $nama_dokter . '"
        }
      }';

    $curl = curl_init();
    // echo '<pre>' . $allergy . '</pre>';

    curl_setopt_array($curl, array(
      CURLOPT_URL => $this->fhirurl . '/AllergyIntolerance',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . json_decode($this->getToken())->access_token),
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $allergy
    ));

    $response = curl_exec($curl);

    $decoded = json_decode($response);
    $id_allergy = (is_object($decoded) && isset($decoded->id)) ? $decoded->id : null;
    $pesan = 'Gagal mengirim allergy platform Satu Sehat!!';
    if ($id_allergy) {
      $mlite_satu_sehat_response = $this->db('mlite_satu_sehat_response')->where('no_rawat', $no_rawat)->oneArray();
      if ($mlite_satu_sehat_response) {
        $this->db('mlite_satu_sehat_response')
          ->where('no_rawat', $no_rawat)
          ->save([
            'no_rawat' => $no_rawat,
            'id_allergy' => $id_allergy
          ]);
      } else {
        $this->db('mlite_satu_sehat_response')
          ->save([
            'no_rawat' => $no_rawat,
            'id_allergy' => $id_allergy
          ]);
      }
      $pesan = 'Sukses mengirim allergy platform Satu Sehat!!';
    }

    curl_close($curl);

    if ($render) {
      echo $this->draw('allergy.html', ['pesan' => $pesan, 'response' => $response]);
    } else {
      echo $response;
    }
    exit();
  }

  public function getSettings()
  {
    return $this->draw('settings.html', ['satu_sehat' => $this->settings->get('satu_sehat'), 'mapping_lokasi' => $this->db('mlite_satu_sehat_lokasi')->toArray(), 'bidang' => $this->db('bidang')->toArray()]);
  }

  public function postSaveSettings()
  {
    foreach ($_POST['satu_sehat'] as $key => $val) {
      $this->settings('satu_sehat', $key, $val);
    }

    $this->notify('success', 'Pengaturan telah disimpan');
    redirect(url([ADMIN, 'satu_sehat', 'settings']));
  }

  public function anyPraktisi()
  {
    $response = [];
    if (isset($_POST['nik_dokter']) && $_POST['nik_dokter'] != '') {
      $response = json_decode($this->getPractitioner($_POST['nik_dokter']));
    }
    return $this->draw('praktisi.html', ['response' => json_encode($response, JSON_PRETTY_PRINT)]);
  }

  public function anyPasien()
  {
    $response = [];
    if (isset($_POST['nik_pasien']) && $_POST['nik_pasien'] != '') {
      $response = json_decode($this->getPatient($_POST['nik_pasien']));
    }
    return $this->draw('pasien.html', ['response' => json_encode($response, JSON_PRETTY_PRINT)]);
  }

  public function getDepartemen()
  {
    $satu_sehat_departemen = $this->db('satu_sehat_mapping_departemen')
        ->join('departemen', 'departemen.dep_id=satu_sehat_mapping_departemen.dep_id')
        ->toArray();

    return $this->draw('departemen.html', [
        'departemen'            => $this->db('departemen')->toArray(),
        'satu_sehat_departemen' => $satu_sehat_departemen
    ]);
  }

  public function getLokasi()
  {
    $poliklinik = $this->db('poliklinik')->select(['kode' => 'kd_poli', 'nama' => 'nm_poli'])->toArray();
    $bangsal    = $this->db('bangsal')->select(['kode' => 'kd_bangsal', 'nama' => 'nm_bangsal'])->toArray();
    $lokasi     = array_merge($poliklinik, $bangsal);
 
    $satu_sehat_departemen = $this->db('satu_sehat_mapping_departemen')
        ->join('departemen', 'departemen.dep_id=satu_sehat_mapping_departemen.dep_id')
        ->toArray();
 
    // RALAN
    $ralan = $this->db('satu_sehat_mapping_lokasi_ralan')
        ->join('satu_sehat_mapping_departemen', 'satu_sehat_mapping_departemen.id_organisasi_satusehat=satu_sehat_mapping_lokasi_ralan.id_organisasi_satusehat')
        ->join('departemen', 'departemen.dep_id=satu_sehat_mapping_departemen.dep_id')
        ->toArray();
    foreach ($ralan as &$r) {
        $poli = $this->db('poliklinik')
            ->select('nm_poli')
            ->where('kd_poli', $r['kd_poli'] ?? '')
            ->oneArray();
        $nm_poli = $poli['nm_poli'] ?? '';
        $r['display_nama'] = $r['nama'] . ($nm_poli ? ' — ' . $nm_poli : '');
        $r['tipe'] = 'ralan';
    }
    unset($r);
 
    // RANAP
    $ranap = $this->db('satu_sehat_mapping_lokasi_ranap')
        ->join('satu_sehat_mapping_departemen', 'satu_sehat_mapping_departemen.id_organisasi_satusehat=satu_sehat_mapping_lokasi_ranap.id_organisasi_satusehat')
        ->join('departemen', 'departemen.dep_id=satu_sehat_mapping_departemen.dep_id')
        ->join('kamar', 'kamar.kd_kamar=satu_sehat_mapping_lokasi_ranap.kd_kamar')
        ->join('bangsal', 'bangsal.kd_bangsal=kamar.kd_bangsal')
        ->toArray();
    foreach ($ranap as &$r) {
        $nama_dep = $r['nama']     ?? '';
        $kd_kamar = $r['kd_kamar'] ?? '';
        $r['display_nama'] = $nama_dep . ($kd_kamar ? ' — ' . $kd_kamar : '');
        $r['tipe'] = 'ranap';
    }
    unset($r);
 
    // DEPO
    $depo = $this->db('satu_sehat_mapping_lokasi_depo_farmasi')
        ->join('satu_sehat_mapping_departemen', 'satu_sehat_mapping_departemen.id_organisasi_satusehat=satu_sehat_mapping_lokasi_depo_farmasi.id_organisasi_satusehat')
        ->join('departemen', 'departemen.dep_id=satu_sehat_mapping_departemen.dep_id')
        ->toArray();
    foreach ($depo as &$r) { $r['display_nama'] = $r['nama']; $r['tipe'] = 'depo'; }
    unset($r);
 
    // RUANG LAB
    $ruanglab = $this->db('satu_sehat_mapping_lokasi_ruanglab')
        ->join('satu_sehat_mapping_departemen', 'satu_sehat_mapping_departemen.id_organisasi_satusehat=satu_sehat_mapping_lokasi_ruanglab.id_organisasi_satusehat')
        ->join('departemen', 'departemen.dep_id=satu_sehat_mapping_departemen.dep_id')
        ->toArray();
    foreach ($ruanglab as &$r) { $r['display_nama'] = $r['nama']; $r['tipe'] = 'ruanglab'; }
    unset($r);
 
    // RUANG LAB PA
    $ruanglabpa = $this->db('satu_sehat_mapping_lokasi_ruanglabpa')
        ->join('satu_sehat_mapping_departemen', 'satu_sehat_mapping_departemen.id_organisasi_satusehat=satu_sehat_mapping_lokasi_ruanglabpa.id_organisasi_satusehat')
        ->join('departemen', 'departemen.dep_id=satu_sehat_mapping_departemen.dep_id')
        ->toArray();
    foreach ($ruanglabpa as &$r) { $r['display_nama'] = $r['nama']; $r['tipe'] = 'labpa'; }
    unset($r);
 
    // RUANG OK
    $ruangok = $this->db('satu_sehat_mapping_lokasi_ruangok')
        ->join('satu_sehat_mapping_departemen', 'satu_sehat_mapping_departemen.id_organisasi_satusehat=satu_sehat_mapping_lokasi_ruangok.id_organisasi_satusehat')
        ->join('departemen', 'departemen.dep_id=satu_sehat_mapping_departemen.dep_id')
        ->toArray();
    foreach ($ruangok as &$r) { $r['display_nama'] = $r['nama']; $r['tipe'] = 'ok'; }
    unset($r);
 
    // RUANG RAD
    $ruangrad = $this->db('satu_sehat_mapping_lokasi_ruangrad')
        ->join('satu_sehat_mapping_departemen', 'satu_sehat_mapping_departemen.id_organisasi_satusehat=satu_sehat_mapping_lokasi_ruangrad.id_organisasi_satusehat')
        ->join('departemen', 'departemen.dep_id=satu_sehat_mapping_departemen.dep_id')
        ->toArray();
    foreach ($ruangrad as &$r) { $r['display_nama'] = $r['nama']; $r['tipe'] = 'rad'; }
    unset($r);
 
    $all_lokasi = array_merge($ralan, $ranap, $depo, $ruanglab, $ruanglabpa, $ruangok, $ruangrad);
 
    return $this->draw('lokasi.html', [
        'lokasi'                => $lokasi,
        'satu_sehat_departemen' => $satu_sehat_departemen,
        'satu_sehat_lokasi'     => $all_lokasi,
    ]);
  }

  public function postSaveLokasi()
  {
    $kode      = $_POST['kode']                    ?? '';
    $tipe      = $_POST['tipe']                    ?? '';
    $id_org    = $_POST['id_organisasi_satusehat'] ?? '';
    $id_lokasi = $_POST['id_lokasi_satusehat']     ?? '';
    $longitude = $_POST['longitude']               ?? '';
    $latitude  = $_POST['latitude']                ?? '';
    $altittude = $_POST['altittude']               ?? '';
 
    $table_map = [
        'ralan'    => ['table' => 'satu_sehat_mapping_lokasi_ralan',       'kd_col' => 'kd_poli'],
        'ranap'    => ['table' => 'satu_sehat_mapping_lokasi_ranap',        'kd_col' => 'kd_kamar'],
        'depo'     => ['table' => 'satu_sehat_mapping_lokasi_depo_farmasi', 'kd_col' => 'kd_depo'],
        'ruanglab' => ['table' => 'satu_sehat_mapping_lokasi_ruanglab',     'kd_col' => 'kd_ruang'],
        'labpa'    => ['table' => 'satu_sehat_mapping_lokasi_ruanglabpa',   'kd_col' => 'kd_ruang'],
        'ok'       => ['table' => 'satu_sehat_mapping_lokasi_ruangok',      'kd_col' => 'kd_ruang'],
        'rad'      => ['table' => 'satu_sehat_mapping_lokasi_ruangrad',     'kd_col' => 'kd_ruang'],
    ];
 
    $table  = $table_map[$tipe]['table']  ?? 'satu_sehat_mapping_lokasi_ralan';
    $kd_col = $table_map[$tipe]['kd_col'] ?? 'kd_poli';
 
    $nama_lokasi = $this->getNamaLokasi($tipe, $kode);
    $org_id      = $this->organizationid;
    $token       = json_decode($this->getToken())->access_token;
 
    // ===== Build FHIR Location payload =====
    $payload = [
        'resourceType' => 'Location',
        'identifier'   => [[
            'system' => 'http://sys-ids.kemkes.go.id/location/' . $org_id,
            'value'  => $kode,
        ]],
        'status'      => 'active',
        'name'        => $nama_lokasi,
        'mode'        => 'instance',
        'physicalType' => [
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/location-physical-type',
                'code'    => 'ro',
                'display' => 'Room',
            ]],
        ],
        'managingOrganization' => [
            'reference' => 'Organization/' . $org_id,
        ],
    ];
 
    if (!empty($longitude) && !empty($latitude)) {
        $payload['position'] = [
            'longitude' => (float)$longitude,
            'latitude'  => (float)$latitude,
            'altitude'  => (float)$altittude,
        ];
    }
 
    if (isset($_POST['simpan'])) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->fhirurl . '/Location',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);
        $response  = curl_exec($curl);
        curl_close($curl);
 
        $resp_json      = json_decode($response);
        $id_lokasi_baru = $resp_json->id ?? '';
 
        if ($id_lokasi_baru) {
            $this->db($table)->save([
                $kd_col                   => $kode,
                'id_organisasi_satusehat' => $id_org,
                'id_lokasi_satusehat'     => $id_lokasi_baru,
                'longitude'               => $longitude,
                'latitude'                => $latitude,
                'altittude'               => $altittude,
            ]);
            $this->notify('success', 'Mapping lokasi berhasil disimpan! ID: ' . $id_lokasi_baru);
        } else {
            $err = $resp_json->issue[0]->details->text ?? ($resp_json->issue[0]->diagnostics ?? 'Unknown error');
            $this->notify('danger', 'Gagal simpan ke Satu Sehat: ' . $err);
        }
    }
 
    if (isset($_POST['update'])) {
        if (empty($id_lokasi)) {
            $this->notify('danger', 'ID Lokasi Satu Sehat tidak ditemukan, tidak bisa update');
            redirect(url([ADMIN, 'satu_sehat', 'lokasi']));
            return;
        }
 
        $payload['id'] = $id_lokasi;
 
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->fhirurl . '/Location/' . $id_lokasi,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
 
        $resp_json  = json_decode($response);
        $updated_id = $resp_json->id ?? '';
 
        if ($updated_id) {
            $this->db($table)
                ->where('id_lokasi_satusehat', $id_lokasi)
                ->save([
                    $kd_col                   => $kode,
                    'id_organisasi_satusehat' => $id_org,
                    'longitude'               => $longitude,
                    'latitude'                => $latitude,
                    'altittude'               => $altittude,
                ]);
            $this->notify('success', 'Mapping lokasi berhasil diupdate!');
        } else {
            $err = $resp_json->issue[0]->details->text ?? ($resp_json->issue[0]->diagnostics ?? 'Unknown error');
            $this->notify('danger', 'Gagal update ke Satu Sehat: ' . $err);
        }
    }
 
    if (isset($_POST['hapus'])) {
        if (empty($id_lokasi)) {
            $this->notify('danger', 'ID Lokasi Satu Sehat tidak ditemukan, tidak bisa hapus');
            redirect(url([ADMIN, 'satu_sehat', 'lokasi']));
            return;
        }
 
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->fhirurl . '/Location/' . $id_lokasi,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
        ]);
        $response  = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
 
        if (in_array($http_code, [200, 204])) {
            $tables = array_column($table_map, 'table');
            foreach ($tables as $t) {
                $this->db($t)->where('id_lokasi_satusehat', $id_lokasi)->delete();
            }
            $this->notify('success', 'Mapping lokasi berhasil dihapus dari Satu Sehat dan lokal!');
        } else {
            $resp_json = json_decode($response);
            $err = $resp_json->issue[0]->details->text ?? ($resp_json->issue[0]->diagnostics ?? 'HTTP ' . $http_code);
            $this->notify('danger', 'Gagal hapus dari Satu Sehat: ' . $err);
        }
    }
 
    redirect(url([ADMIN, 'satu_sehat', 'lokasi']));
  }

  private function getNamaLokasi(string $tipe, string $kode): string
  {
    switch ($tipe) {
        case 'ralan':
            $row = $this->db('poliklinik')->select('nm_poli')->where('kd_poli', $kode)->oneArray();
            return $row['nm_poli'] ?? $kode;
        case 'ranap':
            $row = $this->db('kamar')->select('kd_kamar')->where('kd_kamar', $kode)->oneArray();
            return $row['kd_kamar'] ?? $kode;
        case 'depo':
        case 'ruanglab':
        case 'labpa':
        case 'ok':
        case 'rad':
        default:
            $row = $this->db('bangsal')->select('nm_bangsal')->where('kd_bangsal', $kode)->oneArray();
            return $row['nm_bangsal'] ?? $kode;
    }
  }

  public function getMappingPraktisi()
  {
    $this->_addHeaderFiles();
    $apotek_setting = $this->settings->get('satu_sehat.praktisiapotek');
    $lab_setting = $this->settings->get('satu_sehat.praktisilab');
    $mapping_praktisi = $this->db('mlite_satu_sehat_mapping_praktisi')
      ->join('dokter', 'dokter.kd_dokter=mlite_satu_sehat_mapping_praktisi.kd_dokter')
      ->toArray();
    $mapping_praktisi_apoteker = $this->db('mlite_satu_sehat_mapping_praktisi')
      ->select('pegawai.nama as nm_dokter, mlite_satu_sehat_mapping_praktisi.*')
      ->join('pegawai', 'pegawai.nik=mlite_satu_sehat_mapping_praktisi.kd_dokter')->toArray();

    $combined = array_merge($mapping_praktisi, $mapping_praktisi_apoteker);
    $unique = [];
    $seen = [];

    foreach ($combined as $item) {
      $key = $item['kd_dokter'];
      if (!isset($seen[$key])) {
        $seen[$key] = true;
        $unique[] = $item;
      }
    }

    $dokter = $this->db('dokter')->where('status', '1')->toArray();
    $apoteker = $this->db('pegawai')->where('stts_aktif', 'AKTIF')->where('bidang', $apotek_setting)->toArray();
    $lab = $this->db('pegawai')->where('stts_aktif', 'AKTIF')->where('bidang', $lab_setting)->toArray();
    $lab_apoteker = array_merge($lab, $apoteker);
    return $this->draw('mapping.praktisi.html', ['mapping_praktisi' => $unique, 'dokter' => $dokter, 'apoteker' => $lab_apoteker]);
  }

  public function postSaveMappingPraktisi()
  {
    if (isset($_POST['simpan'])) {
      $kd_dokter = (is_null($_POST['dokter'])) ? $_POST['medis'] : $_POST['dokter'];
      $nik = $this->core->getPegawaiInfo('no_ktp', $kd_dokter);
      $bidang = $this->core->getPegawaiInfo('bidang', $kd_dokter);
      $send_json = json_decode($this->getPractitioner($nik))->entry[0]->resource->id;
      $apotek_setting = $this->settings->get('satu_sehat.praktisiapotek');
      $lab_setting = $this->settings->get('satu_sehat.praktisilab');
      $jenis_praktisi = 'Dokter';
      if ($bidang == $apotek_setting) {
        $jenis_praktisi = 'Apoteker';
      }
      if ($bidang == $lab_setting) {
        $jenis_praktisi = 'Laboratorium';
      }
      if ($send_json != '') {
        $query = $this->db('mlite_satu_sehat_mapping_praktisi')->save(
          [
            'practitioner_id' => $send_json,
            'kd_dokter' => $_POST['dokter'],
            'jenis_praktisi' => $jenis_praktisi,
          ]
        );
        if ($query) {
          $this->notify('success', 'Mapping praktisi telah disimpan ');
        } else {
          $this->notify('danger', 'Mapping depapraktisirtemen gagal disimpan');
        }
      }
    }

    if (isset($_POST['hapus'])) {
      $query = $this->db('mlite_satu_sehat_mapping_praktisi')
        ->where('kd_dokter', $_POST['dokter'])
        ->delete();
      if ($query) {
        $this->notify('success', 'Mapping praktisi telah dihapus ');
      }
    }

    redirect(url([ADMIN, 'satu_sehat', 'mappingpraktisi']));
  }

  public function getCodeDrugForm($keyword)
  {
    $url = 'https://terminology.hl7.org/6.4.0/CodeSystem-v3-orderableDrugForm.json';

    // Get JSON data from the URL
    $json = file_get_contents($url);

    // Decode JSON to PHP array
    $data = json_decode($json, true);

    // Access values
    foreach ($data['concept'] as $value) {
      if ($value['display'] === $keyword) {
        return $value['code'];
      }
    }
  }

  public function searchObat($keyword)
  {

    $url = $this->settings->get('satu_sehat.authurl');
    $parsed = parse_url($url);
    $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];

    if ($this->getAccessToken() === '') {
      return ['error' => 'Gagal mendapatkan access token'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $baseUrl . '/kfa-v2/products/all?page=1&size=5&product_type=farmasi&keyword=' . urlencode($keyword),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $this->getAccessToken(),
        'Accept: application/json'
      ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
  }

  public function searchIdentifierObat($keyword)
  {

    $url = $this->settings->get('satu_sehat.authurl');
    $parsed = parse_url($url);
    $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];

    if ($this->getAccessToken() === '') {
      return ['error' => 'Gagal mendapatkan access token'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $baseUrl . '/kfa-v2/products?identifier=kfa&code=' . urlencode($keyword),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $this->getAccessToken(),
        'Accept: application/json'
      ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
  }

  public function getMappingObat()
  {
    $this->_addHeaderFiles();
    $databarang  = $this->db('databarang')->where('status', '1')->toArray();
    $mapping_obat = $this->db('satu_sehat_mapping_obat')->toArray(); // FIX: ganti tabel
    return $this->draw('mapping.obat.html', [
        'databarang'              => $databarang,
        'mapping_obat_satu_sehat' => $mapping_obat
    ]);
  }

  public function getMappingObatSearch()
  {
    echo json_encode($this->searchObat($_GET['keyword']));
    exit();
  }

  public function regexZatAktif($string)
  {
    if (preg_match('/([\d\.]+)\s*([^\d\s]+)/', $string, $matches)) {
      return [
        'value' => $matches[1],
        'unit' => $matches[2],
      ];
    }

    return null;
  }

  public function postSaveObat()
  {
    if (isset($_POST['simpan'])) {
 
        $cari_obat       = $this->searchIdentifierObat($_POST['select_kfa']);
        $get_drug_form   = $this->getCodeDrugForm($cari_obat['result']['uom']['name']);
        $nama_satuan_den = $cari_obat['result']['uom']['name'];
        if ($get_drug_form == '') {
            $get_drug_form   = $this->getCodeDrugForm(ucfirst($cari_obat['result']['rute_pemberian']['code']));
            $nama_satuan_den = ucfirst($cari_obat['result']['rute_pemberian']['code']);
        }
        $numerator_value = $this->regexZatAktif($cari_obat['result']['active_ingredients'][0]['kekuatan_zat_aktif']);
 
        // FIX: ganti tabel + sesuaikan nama kolom ke satu_sehat_mapping_obat
        $query = $this->db('satu_sehat_mapping_obat')->save([
            'kode_brng'          => $_POST['kode_brng'],
            'obat_code'          => $_POST['select_kfa'],
            'obat_system'        => 'http://sys-ids.kemkes.go.id/kfa',
            'obat_display'       => $cari_obat['result']['name'],
            'form_code'          => $cari_obat['result']['dosage_form']['code'],
            'form_system'        => 'http://terminology.kemkes.go.id/CodeSystem/medication-form',
            'form_display'       => $cari_obat['result']['dosage_form']['name'],
            'numerator_code'     => $numerator_value['unit'],
            'numerator_system'   => 'http://unitsofmeasure.org',
            'denominator_code'   => $get_drug_form,
            'denominator_system' => 'http://snomed.info/sct',
            'route_code'         => $cari_obat['result']['rute_pemberian']['code'],
            'route_system'       => 'http://www.whocc.no/atc',
            'route_display'      => $cari_obat['result']['rute_pemberian']['name'],
            'type'               => $_POST['type'],
        ]);
 
        if ($query) {
            $this->notify('success', 'Mapping obat telah disimpan');
        } else {
            $this->notify('danger', 'Mapping obat gagal disimpan');
        }
    }
 
    if (isset($_POST['hapus'])) {
        // FIX: ganti tabel
        $query = $this->db('satu_sehat_mapping_obat')
            ->where('kode_brng', $_POST['kode_brng'])
            ->delete();
        if ($query) {
            $this->notify('success', 'Mapping obat telah dihapus');
        }
    }
 
    redirect(url([ADMIN, 'satu_sehat', 'mappingobat']));
  }

  public function getResponse()
  {
    $this->_addHeaderFiles();
    return $this->draw('response.html');
  }

  public function postResponseApi()
  {
    $this->_addHeaderFiles();

    $start_date = isset($_GET['tanggal_awal']) && $_GET['tanggal_awal'] !== '' ? $_GET['tanggal_awal'] : date('Y-m-d');
    $end_date   = isset($_GET['tanggal_akhir']) && $_GET['tanggal_akhir'] !== '' ? $_GET['tanggal_akhir'] : $start_date;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) $start_date = date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date))   $end_date   = $start_date;
    if ($start_date > $end_date) { $tmp = $start_date; $start_date = $end_date; $end_date = $tmp; }

    $start      = intval($_POST['start']  ?? 0);
    $length     = intval($_POST['length'] ?? 10);
    $draw       = intval($_POST['draw']   ?? 1);
    $searchTerm = $_POST['search']['value'] ?? '';

    $total = $this->db('reg_periksa')
        ->where('reg_periksa.tgl_registrasi', '>=', $start_date)
        ->where('reg_periksa.tgl_registrasi', '<=', $end_date)
        ->where('stts', '!=', 'Batal')
        ->where('status_lanjut', 'Ralan')
        ->count();

    $query = $this->db('reg_periksa')
        ->join('pasien', 'pasien.no_rkm_medis = reg_periksa.no_rkm_medis')
        ->join('dokter', 'dokter.kd_dokter = reg_periksa.kd_dokter')
        ->leftJoin('pegawai', 'pegawai.nik = reg_periksa.kd_dokter')
        ->where('reg_periksa.tgl_registrasi', '>=', $start_date)
        ->where('reg_periksa.tgl_registrasi', '<=', $end_date)
        ->where('stts', '!=', 'Batal')
        ->where('status_lanjut', 'Ralan');

    if ($searchTerm) {
        $query->where(function ($q) use ($searchTerm) {
            $q->where('reg_periksa.no_rawat', 'LIKE', "%$searchTerm%")
              ->orWhere('reg_periksa.no_rkm_medis', 'LIKE', "%$searchTerm%")
              ->orWhere('reg_periksa.tgl_registrasi', 'LIKE', "%$searchTerm%")
              ->orWhere('pasien.nm_pasien', 'LIKE', "%$searchTerm%")
              ->orWhere('pasien.no_ktp', 'LIKE', "%$searchTerm%")
              ->orWhere('dokter.nm_dokter', 'LIKE', "%$searchTerm%")
              ->orWhere('reg_periksa.kd_dokter', 'LIKE', "%$searchTerm%")
              ->orWhere('pegawai.no_ktp', 'LIKE', "%$searchTerm%");
        });
    }

    $filteredTotal = $query->count();

    $query_data = $query->select([
        'reg_periksa.*',
        'nm_pasien'     => 'pasien.nm_pasien',
        'no_ktp_pasien' => 'pasien.no_ktp',
        'nm_dokter'     => 'dokter.nm_dokter',
        'no_ktp_dokter' => 'pegawai.no_ktp'
    ])->limit($length)->offset($start)->toArray();

    $data_response = [];
    $debug_lokasi  = []; // ← debug collector

    foreach ($query_data as $row) {

        $satu_sehat_response = $this->db('mlite_satu_sehat_response')
            ->where('no_rawat', $row['no_rawat'])->oneArray();

        $row['no_rawat_converted'] = convertNoRawat($row['no_rawat']);
        $row['nm_poli']            = $this->core->getPoliklinikInfo('nm_poli', $row['kd_poli']);

        // Mapping Praktisi (tetap mlite)
        $praktisi = $this->db('mlite_satu_sehat_mapping_praktisi')
            ->where('kd_dokter', $row['kd_dokter'])->oneArray();
        $row['praktisi_id'] = (is_array($praktisi) && isset($praktisi['practitioner_id']))
            ? $praktisi['practitioner_id'] : '';

        // Billing
        $billing           = $this->db('billing')->where('no_rawat', $row['no_rawat'])->oneArray();
        $pemeriksaan_ralan = $this->db('pemeriksaan_ralan')->where('no_rawat', $row['no_rawat'])->oneArray();
        $row['tgl_pulang'] = isset_or($billing['tgl_byr'], isset_or($pemeriksaan_ralan['tgl_perawatan'], ''));

        // Handle Ranap
        if ($row['status_lanjut'] == 'Ranap') {
            $row['kd_kamar'] = $this->core->getKamarInapInfo('kd_kamar', $row['no_rawat']);
            $row['kd_poli']  = $this->core->getKamarInfo('kd_bangsal', $row['kd_kamar']);
            $row['nm_poli']  = $this->core->getBangsalInfo('nm_bangsal', $row['kd_poli']);
        }

        // Mapping Lokasi + DEBUG
        if ($row['status_lanjut'] == 'Ranap') {
            $mapping_lokasi = $this->db('satu_sehat_mapping_lokasi_ranap')
                ->where('kd_kamar', $row['kd_kamar'] ?? '')->oneArray();
            $debug_lokasi[] = [
                'no_rawat'      => $row['no_rawat'],
                'status_lanjut' => $row['status_lanjut'],
                'query_by'      => 'kd_kamar',
                'query_val'     => $row['kd_kamar'] ?? '',
                'result'        => $mapping_lokasi,
                'id_organisasi' => $mapping_lokasi['id_organisasi_satusehat'] ?? 'KOSONG',
                'id_lokasi'     => $mapping_lokasi['id_lokasi_satusehat'] ?? 'KOSONG',
            ];
        } else {
            $mapping_lokasi = $this->db('satu_sehat_mapping_lokasi_ralan')
                ->where('kd_poli', $row['kd_poli'])->oneArray();
            $debug_lokasi[] = [
                'no_rawat'      => $row['no_rawat'],
                'status_lanjut' => $row['status_lanjut'],
                'query_by'      => 'kd_poli',
                'query_val'     => $row['kd_poli'],
                'result'        => $mapping_lokasi,
                'id_organisasi' => $mapping_lokasi['id_organisasi_satusehat'] ?? 'KOSONG',
                'id_lokasi'     => $mapping_lokasi['id_lokasi_satusehat'] ?? 'KOSONG',
            ];
        }
        $row['id_organisasi'] = isset_or($mapping_lokasi['id_organisasi_satusehat'], '');
        $row['id_lokasi']     = isset_or($mapping_lokasi['id_lokasi_satusehat'], '');

        // Pemeriksaan
        $row['pemeriksaan'] = $this->db('pemeriksaan_ralan')
            ->where('no_rawat', $row['no_rawat'])
            ->limit(1)->desc('tgl_perawatan')->oneArray();
        if ($row['status_lanjut'] == 'Ranap') {
            $row['pemeriksaan'] = $this->db('pemeriksaan_ranap')
                ->where('no_rawat', $row['no_rawat'])
                ->limit(1)->desc('tgl_perawatan')->oneArray();
        }

        // Diagnosa & Prosedur
        $row['diagnosa_pasien'] = $this->db('diagnosa_pasien')
            ->join('penyakit', 'penyakit.kd_penyakit=diagnosa_pasien.kd_penyakit')
            ->where('no_rawat', $row['no_rawat'])
            ->where('diagnosa_pasien.status', $row['status_lanjut'])
            ->where('prioritas', '1')->oneArray();
        $row['prosedur_pasien'] = $this->db('prosedur_pasien')
            ->join('icd9', 'icd9.kode=prosedur_pasien.kode')
            ->where('no_rawat', $row['no_rawat'])
            ->where('prosedur_pasien.status', $row['status_lanjut'])
            ->where('prioritas', '1')->oneArray();

        $row['adime_gizi']   = $this->db('catatan_adime_gizi')->where('no_rawat', $row['no_rawat'])->oneArray();
        $row['immunization'] = $this->db('resep_obat')
            ->join('resep_dokter', 'resep_dokter.no_resep=resep_obat.no_resep')
            ->join('satu_sehat_mapping_vaksin', 'satu_sehat_mapping_vaksin.kode_brng=resep_dokter.kode_brng')
            ->where('no_rawat', $row['no_rawat'])->oneArray();

        $row['clinical_impression'] = isset_or($row['pemeriksaan']['penilaian'], '');

        $row['medications']          = $this->db('resep_obat')
            ->join('resep_dokter', 'resep_dokter.no_resep=resep_obat.no_resep')
            ->where('no_rawat', $row['no_rawat'])->oneArray();
        $row['medication_request']   = isset_or($row['medications']['tgl_peresepan'], '');
        $row['medication_dispense']  = isset_or($row['medications']['tgl_perawatan'], '');
        $row['medication_statement'] = isset_or($row['medications']['tgl_penyerahan'], '');

        $row['permintaan_radiologi']        = $this->db('permintaan_radiologi')->where('no_rawat', $row['no_rawat'])->oneArray();
        $row['service_request_radiologi']   = isset_or($row['permintaan_radiologi']['tgl_permintaan'], '');
        $row['specimen_radiologi']          = isset_or($row['permintaan_radiologi']['tgl_sampel'], '');
        $row['observation_radiologi']       = isset_or($row['permintaan_radiologi']['tgl_hasil'], '');
        $row['diagnostic_report_radiologi'] = isset_or($row['permintaan_radiologi']['tgl_hasil'], '');

        $row['permintaan_lab']           = $this->db('permintaan_lab')->where('no_rawat', $row['no_rawat'])->oneArray();
        $row['service_request_lab_pk']   = isset_or($row['permintaan_lab']['tgl_permintaan'], '');
        $row['service_request_lab_pa']   = isset_or($row['permintaan_lab']['tgl_permintaan'], '');
        $row['service_request_lab_mb']   = isset_or($row['permintaan_lab']['tgl_permintaan'], '');
        $row['specimen_lab_pk']          = isset_or($row['permintaan_lab']['tgl_sampel'], '');
        $row['specimen_lab_pa']          = $row['permintaan_lab'];
        $row['specimen_lab_mb']          = $row['permintaan_lab'];
        $row['observation_lab_pk']       = isset_or($row['permintaan_lab']['tgl_hasil'], '');
        $row['observation_lab_pa']       = $row['permintaan_lab'];
        $row['observation_lab_mb']       = $row['permintaan_lab'];
        $row['diagnostic_report_lab_pk'] = isset_or($row['permintaan_lab']['tgl_hasil'], '');
        $row['diagnostic_report_lab_pa'] = $row['permintaan_lab'];
        $row['diagnostic_report_lab_mb'] = $row['permintaan_lab'];
        $row['care_plan']                = isset_or($row['pemeriksaan']['rtl'], '');

        $allergy_list = [
            ['deskripsi' => 'Allergy to food',          'snomed_ct' => '414285001', 'icd_10' => 'T78.1', 'category' => 'food'],
            ['deskripsi' => 'Allergy to drug',          'snomed_ct' => '416098002', 'icd_10' => 'T88.7', 'category' => 'drug'],
            ['deskripsi' => 'Allergy to nutraceutical', 'snomed_ct' => '300910009', 'icd_10' => 'J30.1', 'category' => 'environment'],
            ['deskripsi' => 'Allergy to dust',          'snomed_ct' => '232350006', 'icd_10' => 'J30.8', 'category' => 'environment'],
            ['deskripsi' => 'Allergic disposition',     'snomed_ct' => '609328004', 'icd_10' => 'T78.4', 'category' => 'environment'],
        ];
        $allergy_icd10  = array_column($allergy_list, 'icd_10');
        $row['allergy'] = [];
        $allergy        = $this->db('diagnosa_pasien')
            ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
            ->where('no_rawat', $row['no_rawat'])
            ->where('diagnosa_pasien.status', $row['status_lanjut'])
            ->in('diagnosa_pasien.kd_penyakit', $allergy_icd10)->oneArray();
        $allergy_map    = array_column($allergy_list, null, 'icd_10');
        if (!empty($allergy) && isset($allergy_map[$allergy['kd_penyakit']])) {
            $row['allergy'][] = $allergy_map[$allergy['kd_penyakit']];
        }

        $row['questionnaire'] = $this->db('catatan_perawatan')
            ->where('no_rawat', $row['no_rawat'])
            ->where('catatan', 'KPS')->oneArray();

        // IDs response
        $row['id_encounter']                = isset_or($satu_sehat_response['id_encounter'], '');
        $row['id_condition']                = isset_or($satu_sehat_response['id_condition'], '');
        $row['id_clinical_impression']      = isset_or($satu_sehat_response['id_clinical_impression'], '');
        $row['id_observation_ttvtensi']     = isset_or($satu_sehat_response['id_observation_ttvtensi'], '');
        $row['id_observation_ttvnadi']      = isset_or($satu_sehat_response['id_observation_ttvnadi'], '');
        $row['id_observation_ttvrespirasi'] = isset_or($satu_sehat_response['id_observation_ttvrespirasi'], '');
        $row['id_observation_ttvsuhu']      = isset_or($satu_sehat_response['id_observation_ttvsuhu'], '');
        $row['id_observation_ttvspo2']      = isset_or($satu_sehat_response['id_observation_ttvspo2'], '');
        $row['id_observation_ttvgcs']       = isset_or($satu_sehat_response['id_observation_ttvgcs'], '');
        $row['id_observation_ttvtinggi']    = isset_or($satu_sehat_response['id_observation_ttvtinggi'], '');
        $row['id_observation_ttvberat']     = isset_or($satu_sehat_response['id_observation_ttvberat'], '');
        $row['id_observation_ttvperut']     = isset_or($satu_sehat_response['id_observation_ttvperut'], '');
        $row['id_observation_ttvkesadaran'] = isset_or($satu_sehat_response['id_observation_ttvkesadaran'], '');
        $row['id_procedure']                = isset_or($satu_sehat_response['id_procedure'], '');
        $row['id_composition']              = isset_or($satu_sehat_response['id_composition'], '');
        $row['id_medication_for_request']   = isset_or($satu_sehat_response['id_medication_for_request'], '');
        $row['id_medication_request']       = isset_or($satu_sehat_response['id_medication_request'], '');
        $row['id_medication_for_dispense']  = isset_or($satu_sehat_response['id_medication_for_dispense'], '');
        $row['id_medication_dispense']      = isset_or($satu_sehat_response['id_medication_dispense'], '');
        $row['id_immunization']             = isset_or($satu_sehat_response['id_immunization'], '');
        $row['id_medication_statement']     = isset_or($satu_sehat_response['id_medication_statement'], '');
        $row['id_rad_request']              = isset_or($satu_sehat_response['id_rad_request'], '');
        $row['id_rad_specimen']             = isset_or($satu_sehat_response['id_rad_specimen'], '');
        $row['id_rad_observation']          = isset_or($satu_sehat_response['id_rad_observation'], '');
        $row['id_rad_diagnostic']           = isset_or($satu_sehat_response['id_rad_diagnostic'], '');
        $row['id_lab_pk_request']           = isset_or($satu_sehat_response['id_lab_pk_request'], '');
        $row['id_service_request_lab_pa']   = isset_or($satu_sehat_response['id_service_request_lab_pa'], '');
        $row['id_service_request_lab_mb']   = isset_or($satu_sehat_response['id_service_request_lab_mb'], '');
        $row['id_lab_pk_specimen']          = isset_or($satu_sehat_response['id_lab_pk_specimen'], '');
        $row['id_specimen_lab_pa']          = isset_or($satu_sehat_response['id_specimen_lab_pa'], '');
        $row['id_specimen_lab_mb']          = isset_or($satu_sehat_response['id_specimen_lab_mb'], '');
        $row['id_lab_pk_observation']       = isset_or($satu_sehat_response['id_lab_pk_observation'], '');
        $row['id_observation_lab_pa']       = isset_or($satu_sehat_response['id_observation_lab_pa'], '');
        $row['id_observation_lab_mb']       = isset_or($satu_sehat_response['id_observation_lab_mb'], '');
        $row['id_lab_pk_diagnostic']        = isset_or($satu_sehat_response['id_lab_pk_diagnostic'], '');
        $row['id_diagnostic_report_lab_pa'] = isset_or($satu_sehat_response['id_diagnostic_report_lab_pa'], '');
        $row['id_diagnostic_report_lab_mb'] = isset_or($satu_sehat_response['id_diagnostic_report_lab_mb'], '');
        $row['id_careplan']                 = isset_or($satu_sehat_response['id_careplan'], '');
        $row['id_allergy']                  = isset_or($satu_sehat_response['id_allergy'], '');
        $row['id_questionnaire']            = isset_or($satu_sehat_response['id_questionnaire'], '');

        $data_response[] = $row;
    }

    echo json_encode([
        "draw"            => $draw,
        "recordsFiltered" => $filteredTotal,
        "recordsTotal"    => $total,
        "data"            => $data_response,
        "debug_lokasi"    => $debug_lokasi // ← hapus setelah debug selesai
    ]);
    exit();
  }

  public function gen_uuid()
  {
    return sprintf(
      '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      // 32 bits for "time_low"
      mt_rand(0, 0xffff),
      mt_rand(0, 0xffff),

      // 16 bits for "time_mid"
      mt_rand(0, 0xffff),

      // 16 bits for "time_hi_and_version",
      // four most significant bits holds version number 4
      mt_rand(0, 0x0fff) | 0x4000,

      // 16 bits, 8 bits for "clk_seq_hi_res",
      // 8 bits for "clk_seq_low",
      // two most significant bits holds zero and one for variant DCE1.1
      mt_rand(0, 0x3fff) | 0x8000,

      // 48 bits for "node"
      mt_rand(0, 0xffff),
      mt_rand(0, 0xffff),
      mt_rand(0, 0xffff)
    );
  }

  public function ran_char()
  {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $randomChars = '';

    for ($i = 0; $i < 3; $i++) {
      $randomChars .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $randomChars;
  }

  public function convertTimeSatset($waktu)
  {
    $zonawaktu = '-7 hours';
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WITA') {
      $zonawaktu = '-8 hours';
    }
    if ($this->settings->get('satu_sehat.zonawaktu') == 'WIT') {
      $zonawaktu = '-9 hours';
    }
    $DateTime = new \DateTime($waktu);
    $DateTime->modify($zonawaktu);
    return $DateTime->format("Y-m-d\TH:i:s");
  }

  public function getKyc()
  {

    $this->authurl = $this->settings->get('satu_sehat.authurl');
    $this->fhirurl = $this->settings->get('satu_sehat.fhirurl');
    $this->clientid = $this->settings->get('satu_sehat.clientid');
    $this->secretkey = $this->settings->get('satu_sehat.secretkey');
    $this->organizationid = $this->settings->get('satu_sehat.organizationid');

    $client_id = $this->clientid;
    $client_secret = $this->secretkey;
    $auth_url = $this->authurl;
    $api_url = 'https://api-satusehat.kemkes.go.id/kyc/v1/generate-url';
    $environment = 'production';

    // nama petugas/operator Fasilitas Pelayanan Kesehatan (Fasyankes) yang akan melakukan validasi
    $agent_name = $this->core->getUserInfo('fullname', null, true);

    // NIK petugas/operator Fasilitas Pelayanan Kesehatan (Fasyankes) yang akan melakukan validasi
    $agent_nik = $this->core->getPegawaiInfo('no_ktp', $this->core->getUserInfo('username', null, true));

    // auth to satusehat
    $auth_result = $this->authenticateWithOAuth2($client_id, $client_secret, $auth_url);

    // Validate authentication result
    if ($auth_result === null) {
      error_log('Satu Sehat authentication failed: Invalid client credentials or auth URL');
      return $this->draw('error.html', [
        'title' => 'Authentication Error',
        'message' => 'Failed to authenticate with Satu Sehat API. Please check your client credentials and try again.'
      ]);
    }

    // Log successful authentication
    error_log('Satu Sehat authentication successful');

    // Example usage
    try {
      $kyc = new Kyc;
      $json = $kyc->generateUrl($agent_name, $agent_nik, $auth_result, $api_url, $environment);

      $validation_web = json_decode($json, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Satu Sehat KYC JSON Parse Error: ' . json_last_error_msg() . ' - Response: ' . $json);
        return $this->draw('error.html', [
          'title' => 'KYC Response Error',
          'message' => 'Failed to parse KYC response from Satu Sehat API. Please try again later.'
        ]);
      }

      if (!isset($validation_web["data"]["url"])) {
        error_log('Satu Sehat KYC Error: No URL in response - ' . $json);
        return $this->draw('error.html', [
          'title' => 'KYC URL Error',
          'message' => 'KYC URL not found in Satu Sehat API response. Please check your configuration.'
        ]);
      }

      $url = $validation_web["data"]["url"];
      error_log('Satu Sehat KYC URL generated successfully');

      return $this->draw('kyc.html', ['url' => $url]);

    } catch (\Exception $e) {
      error_log('Satu Sehat KYC Exception: ' . $e->getMessage());
      return $this->draw('error.html', [
        'title' => 'KYC Generation Error',
        'message' => 'An error occurred while generating KYC URL: ' . $e->getMessage()
      ]);
    }
  }

  public function authenticateWithOAuth2($clientId, $clientSecret, $tokenUrl)
  {
    $curl = curl_init();
    $params = [
      'grant_type' => 'client_credentials',
      'client_id' => $clientId,
      'client_secret' => $clientSecret
    ];

    curl_setopt_array($curl, array(
      CURLOPT_URL => "$tokenUrl/accesstoken?grant_type=client_credentials",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => http_build_query($params),
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/x-www-form-urlencoded'
      ),
    ));

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);

    curl_close($curl);

    // Check for cURL errors
    if ($curlError) {
      error_log('Satu Sehat OAuth2 cURL Error: ' . $curlError);
      return null;
    }

    // Check HTTP status code
    if ($httpCode !== 200) {
      error_log('Satu Sehat OAuth2 HTTP Error: ' . $httpCode . ' - Response: ' . $response);
      return null;
    }

    // Parse the response body
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      error_log('Satu Sehat OAuth2 JSON Parse Error: ' . json_last_error_msg() . ' - Response: ' . $response);
      return null;
    }

    // Check if access token exists in response
    if (!isset($data['access_token'])) {
      error_log('Satu Sehat OAuth2 Error: No access token in response - ' . json_encode($data));
      return null;
    }

    // Log successful authentication (without exposing the token)
    error_log('Satu Sehat OAuth2 authentication successful');

    // Return the access token
    return $data['access_token'];
  }

  public function get($url)
  {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
  }

  public function postFHIR($url, $token, $data)
  {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Content-Type: application/fhir+json",
      "Authorization: Bearer $token"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
  }

  private function _addHeaderFiles()
  {
    $this->core->addCSS(url('assets/css/dataTables.bootstrap.min.css'));
    $this->core->addCSS(url('assets/css/fixedColumns.dataTables.min.css'));
    $this->core->addJS(url('assets/jscripts/jquery.dataTables.min.js'));
    $this->core->addJS(url('assets/jscripts/dataTables.bootstrap.min.js'));
    $this->core->addJS(url('assets/jscripts/dataTables.fixedColumns.min.js'));
    $this->core->addCSS(url('assets/css/bootstrap-datetimepicker.css'));
    $this->core->addJS(url('assets/jscripts/moment-with-locales.js'));
    $this->core->addJS(url('assets/jscripts/bootstrap-datetimepicker.js'));
  }
}